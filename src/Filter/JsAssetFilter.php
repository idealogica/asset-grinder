<?php
namespace Idealogica\AssetGrinder\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Filter\BaseNodeFilter;
use Idealogica\AssetGrinder\Exception\AssetGrinderException;

/**
 * Class JsAssetFilter
 * @package Idealogica\AssetGrinder\Filter
 */
class JsAssetFilter extends BaseNodeFilter
{
    /**
     * @var string
     */
    protected $uglifyjsBin;

    /**
     * @var string
     */
    protected $uglifyJsArgs;

    /**
     * @var string
     */
    protected $jsObfuscatorBin;

    /**
     * @var string
     */
    protected $jsObfuscatorArgs;

    /**
     * @var bool
     */
    protected $base64Encode = true;

    /**
     * @var bool
     */
    protected $removeWhiteSpaces = false;

    /**
     * @var string
     */
    protected $licenseStamp;

    /**
     * JsAssetFilter constructor.
     *
     * @param string $uglifyjsBin
     * @param string $jsObfuscatorBin
     * @param string $uglifyJsArgs
     * @param string $jsObfuscatorArgs
     * @param bool $base64Encode
     * @param bool $removeWhiteSpaces
     * @param string|null $licenseStamp
     */
    public function __construct(
        string $uglifyjsBin = null,
        string $jsObfuscatorBin = null,
        string $uglifyJsArgs = null,
        string $jsObfuscatorArgs = null,
        bool $base64Encode = true,
        bool $removeWhiteSpaces = false,
        string $licenseStamp = null
    ) {
        $this->uglifyjsBin = $uglifyjsBin;
        $this->jsObfuscatorBin = $jsObfuscatorBin;
        $this->uglifyJsArgs = $uglifyJsArgs;
        $this->jsObfuscatorArgs = $jsObfuscatorArgs;
        $this->base64Encode = $base64Encode;
        $this->removeWhiteSpaces = $removeWhiteSpaces;
        $this->licenseStamp = $licenseStamp;
    }

    /**
     * @param AssetInterface $asset
     */
    public function filterLoad(AssetInterface $asset): void
    {
    }

    /**
     * @param AssetInterface $asset
     *
     * @throws AssetGrinderException
     */
    public function filterDump(AssetInterface $asset): void
    {
        $this->obfuscate($asset);
        if ($this->base64Encode) {
            $this->base64Encode($asset);
        }
        $this->removeWhiteSpaces($asset)->addLicenseStamp($asset);
    }

    /**
     * @param AssetInterface $asset
     *
     * @return $this
     * @throws AssetGrinderException
     */
    protected function obfuscate(AssetInterface $asset): self
    {
        // input and output files
        $input = '/tmp/jsObfuscationInput.js';
        $output = '/tmp/jsObfuscationOutput.js';
        file_put_contents($input, $asset->getContent());
        // uglify-js
        if ($this->uglifyjsBin) {
            $code = 0;
            $cliOutput = [];
            $cliString = '%s %s -o %s %s 2>&1';
            $uglifyJsArgs = $this->uglifyJsArgs ?: '--compress --mangle --mangle-props regex=/.\\\$\$/';
            $cliString = sprintf($cliString, $this->uglifyjsBin, $uglifyJsArgs, $output, $input);
            exec($cliString, $cliOutput, $code);
            $cliOutput = implode(' ', $cliOutput);
            if ($code !== 0) {
                $this->unlinkTempFiles($input, $output);
                if ($code === 127) {
                    throw new AssetGrinderException('Path to uglify-js executable could not be resolved');
                }
                throw new AssetGrinderException('Uglify-js execution error with output: ' . $cliOutput);
            }
        }
        // javascript-obfuscator
        if ($this->jsObfuscatorBin) {
            // if we created output file by uglifyJs we rename it back to input
            if ($this->uglifyjsBin && file_exists($output)) {
                rename($output, $input);
            }
            $code = 0;
            $cliOutput = [];
            $cliString = "%s %s --output %s %s 2>&1";
            $jsObfuscatorArgs =
                $this->jsObfuscatorArgs ?:
                '--compact true --controlFlowFlattening false --deadCodeInjection false --debugProtection false --debugProtectionInterval false --disableConsoleOutput false --log false --mangle false --renameGlobals false --rotateStringArray true --selfDefending true --stringArray true --stringArrayEncoding false --stringArrayThreshold 0.75 --unicodeEscapeSequence false';
            $cliString = sprintf($cliString, $this->jsObfuscatorBin, $input, $output, $jsObfuscatorArgs);
            exec($cliString, $cliOutput, $code);
            $cliOutput = implode(' ', $cliOutput);
            if ($code !== 0) {
                $this->unlinkTempFiles($input, $output);
                if ($code === 127) {
                    throw new AssetGrinderException('Path to javascript-obfuscator executable could not be resolved');
                }
                throw new AssetGrinderException('Javascript-obfuscator execution error with output: ' . $cliOutput);
            }
        }
        // processing disabled
        if (!$this->uglifyjsBin && !$this->jsObfuscatorBin) {
            rename($input, $output);
        }
        // result checking
        if (!file_exists($output)) {
            throw new AssetGrinderException('Error creating output file.');
        }
        $asset->setContent(file_get_contents($output));
        return $this->unlinkTempFiles($input, $output);
    }

    /**
     * @param AssetInterface $asset
     *
     * @return $this
     */
    protected function base64Encode(AssetInterface $asset): self
    {
        $js = '';
        $content = $asset->getContent();
        $hex = unpack('H*', $content);
        $content = base64_encode($hex[1]);
        $js .= "atob('" . $content . "').";
        $js .= "match(/.{1,2}/g).";
        $js .= "map(function(v){return String.fromCharCode(parseInt(v,16));}).";
        $js .= "join('')";
        $content = "(new Function(" . $js . "))();";
        $asset->setContent($content);
        return $this;
    }

    /**
     * @param AssetInterface $asset
     *
     * @return $this
     */
    protected function removeWhiteSpaces(AssetInterface $asset): self
    {
        if (!$this->removeWhiteSpaces) {
            return $this;
        }
        $asset->setContent(preg_replace('#[\s]+#', ' ', $asset->getContent()));
        return $this;
    }

    /**
     * @param AssetInterface $asset
     *
     * @return $this
     */
    protected function addLicenseStamp(AssetInterface $asset): self
    {
        $content = $asset->getContent();
        $content = $this->licenseStamp . "\n\n" . $content;
        $asset->setContent($content);
        return $this;
    }

    /**
     * @param string $input
     * @param string $output
     *
     * @return $this
     */
    protected function unlinkTempFiles(string $input, string $output): self
    {
        if (file_exists($input)) {
            unlink($input);
        }
        if (file_exists($output)) {
            unlink($output);
        }
        return $this;
    }
}
