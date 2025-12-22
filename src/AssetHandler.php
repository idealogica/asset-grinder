<?php
namespace Idealogica\AssetGrinder;

use Assetic\Asset\StringAsset;
use Assetic\Cache\FilesystemCache;
use Idealogica\AssetGrinder\Exception\AssetGrinderException;
use Idealogica\AssetGrinder\Filter\JsAssetFilter;

/**
 * Class AssetHandler
 * @package Idealogica\AssetGrinder
 */
class AssetHandler
{
    const TYPE_JS = 'js';

    const TYPE_CSS = 'css';

    const PARAM_UGLIFY_JS_ENABLED = 'uglifyJsEnabled';

    const PARAM_UGLIFY_JS_ARGS = 'uglifyJsArgs';

    const PARAM_JS_OBFUSCATOR_ENABLED = 'jsObfuscatorEnabled';

    const PARAM_JS_OBFUSCATOR_ARGS = 'jsObfuscatorArgs';

    const PARAM_BASE_64_ENCODE = 'base64Encode';

    /**
     * @var string
     */
    protected $webServerOrigin;

    /**
     * @var string
     */
    protected $webServerRoot;

    /**
     * @var string
     */
    protected $assetsPath;

    /**
     * @var string
     */
    protected $assetsCachePath;

    /**
     * @var string
     */
    protected $customFileNamePrefix;

    /**
     * @var string
     */
    protected $customUrlPostfix;

    /**
     * @var string
     */
    protected $customTagAttr;

    /**
     * @var string|null
     */
    protected $uglifyJsPath;

    /**
     * @var string|null
     */
    protected $jsObfuscatorPath;

    /**
     * @var string|null
     */
    protected $uglifyJsArgs;

    /**
     * @var string|null
     */
    protected $jsObfuscatorArgs;

    /**
     * @var bool
     */
    protected $base64Encode = true;

    /**
     * @var string
     */
    protected $licenseStamp;

    /**
     * @var bool
     */
    protected $debugMode = false;

    /**
     * AssetHandler constructor.
     *
     * @param string $webServerOrigin
     * @param string $webServerRoot
     * @param string $assetsPath
     * @param string $assetsCachePath
     * @param string|null $uglifyJsPath
     * @param string|null $jsObfuscatorPath
     * @param string|null $uglifyJsArgs
     * @param string|null $jsObfuscatorArgs
     * @param bool $base64Encode
     * @param string|null $customFileNamePrefix
     * @param string|null $customUrlPostfix
     * @param string|null $customTagAttr
     * @param string|null $licenseStamp
     * @param bool $debugMode
     */
    public function __construct(
        string $webServerOrigin,
        string $webServerRoot,
        string $assetsPath,
        string $assetsCachePath,
        string $uglifyJsPath = null,
        string $jsObfuscatorPath = null,
        string $uglifyJsArgs = null,
        string $jsObfuscatorArgs = null,
        bool $base64Encode = true,
        string $customFileNamePrefix = null,
        string $customUrlPostfix = null,
        string $customTagAttr = null,
        string $licenseStamp = null,
        bool $debugMode = false
    ) {
        $this->webServerOrigin = $webServerOrigin;
        $this->webServerRoot = $webServerRoot;
        $this->assetsPath = realpath($assetsPath);
        $this->assetsCachePath = realpath($assetsCachePath);
        $this->uglifyJsPath = $uglifyJsPath;
        $this->jsObfuscatorPath = $jsObfuscatorPath;
        $this->uglifyJsArgs = $uglifyJsArgs;
        $this->jsObfuscatorArgs = $jsObfuscatorArgs;
        $this->base64Encode = $base64Encode;
        $this->customFileNamePrefix = $customFileNamePrefix;
        $this->customUrlPostfix = $customUrlPostfix;
        $this->customTagAttr = $customTagAttr;
        $this->licenseStamp = $licenseStamp;
        $this->debugMode = $debugMode;
    }

    /**
     * @param string $key
     * @param array $assets
     * @param string $type
     * @param bool $returnStaticUrl
     * @param bool $skipAssetUpdates
     * @param array $params
     * @param string|null $customOrigin
     * @param bool $asyncJs
     *
     * @return string
     * @throws AssetGrinderException
     */
    public function buildAssetTag(
        string $key,
        array $assets,
        string $type = self::TYPE_JS,
        bool $returnStaticUrl = true,
        bool $skipAssetUpdates = true,
        array $params = [],
        string $customOrigin = null,
        bool $asyncJs = false
    ): string {
        $assetUrl = $this->buildAssetUrl($key, $assets, $type, $returnStaticUrl, $skipAssetUpdates, $params, $customOrigin);
        return $this->getAssetTag($assetUrl, $type, $asyncJs);
    }

    /**
     * @param string $key
     * @param array $assets
     * @param string $type
     * @param bool $returnStaticUrl
     * @param bool $skipAssetUpdates
     * @param array $params
     * @param string|null $customOrigin
     *
     * @return string
     * @throws AssetGrinderException
     */
    public function buildAssetUrl(
        string $key,
        array $assets,
        string $type = self::TYPE_JS,
        bool $returnStaticUrl = true,
        bool $skipAssetUpdates = true,
        array $params = [],
        string $customOrigin = null
    ): string {
        $asset = $this->buildAssetFile($key, $assets, $type, $skipAssetUpdates, $params);
        return $this->getAssetUrl($asset, $returnStaticUrl, $customOrigin);
    }

    /**
     * @param string $key
     * @param array $assets
     * @param string $type
     * @param bool $skipAssetUpdates
     * @param array $params
     *
     * @return string
     * @throws AssetGrinderException
     */
    public function buildAssetContents(
        string $key,
        array $assets,
        string $type = self::TYPE_JS,
        bool $skipAssetUpdates = true,
        array $params = []
    ): string {
        $asset = $this->buildAssetFile($key, $assets, $type, $skipAssetUpdates, $params);
        return $this->getAssetContents($asset);
    }

    /**
     * Builds one asset from given array of files.
     * Stores result in the public cache to save CPU.
     *
     * @param string $key
     * @param array $assets
     * @param string $type Type of asset to build.
     * @param bool $skipAssetUpdates
     * @param array $params
     *
     * @return string
     * @throws AssetGrinderException
     */
    public function buildAssetFile(
        string $key,
        array $assets,
        string $type = self::TYPE_JS,
        bool $skipAssetUpdates = true,
        array $params = []
    ): string {
        $uglifyJsEnabled = $params[self::PARAM_UGLIFY_JS_ENABLED] ?? true;
        $uglifyJsArgs = $params[self::PARAM_UGLIFY_JS_ARGS] ?? null;
        $jsObfuscatorEnabled = $params[self::PARAM_JS_OBFUSCATOR_ENABLED] ?? true;
        $jsObfuscatorArgs = $params[self::PARAM_JS_OBFUSCATOR_ARGS] ?? null;
        $base64Encode = $params[self::PARAM_BASE_64_ENCODE] ?? null;
        $customFileNamePrefix = $this->customFileNamePrefix ? $this->customFileNamePrefix . '.' : '';
        $link = $customFileNamePrefix . $key . '.' . $type;
        // optimization to avoid multiple md5_file() calls in production
        if (!$this->debugMode && $skipAssetUpdates) {
            return $link;
        }
        // asset generation
        if (!$assets) {
            throw new AssetGrinderException('No assets to build passed');
        }
        $hash = '';
        foreach ($assets as &$asset) {
            if (is_string($asset)) {
                $asset = $this->assetsPath . '/' . $asset;
            }
        }
        unset($asset);
        foreach ($assets as $asset) {
            if (is_string($asset)) {
                $hash .= md5_file($this->getAssetFileName($asset));
            } elseif (is_array($asset)) {
                $hash .= md5($asset[0]);
            }
        }
        $mask = $customFileNamePrefix . $key . '.*' . $type;
        $hash = $customFileNamePrefix . $key . '.' . md5($hash) . '.' . $type;
        $cache = new FilesystemCache($this->assetsCachePath);
        if (!$cache->has($hash)) {
            $obfuscatedAssetContent = '';
            $plainAssetsContent = '';
            foreach ($assets as $asset) {
                $obfuscate = true;
                $assetContent = '';
                if (is_string($asset)) {
                    $assetFileName = $this->getAssetFileName($asset, $obfuscate);
                    $assetContent = file_get_contents($assetFileName);
                } elseif (is_array($asset)) {
                    $assetContent = $asset[0];
                }
                if (! $assetContent) {
                    continue;
                }
                $assetContent .= "\n\n";
                if ($obfuscate) {
                    $obfuscatedAssetContent .= $assetContent;
                } else {
                    $plainAssetsContent .= $assetContent;
                }
            }
            $mergedContent = '';
            if ($obfuscatedAssetContent) {
                $mergedContent .= $this->filterContent(
                    $obfuscatedAssetContent,
                    $type,
                    $uglifyJsEnabled,
                    $jsObfuscatorEnabled,
                    $uglifyJsArgs,
                    $jsObfuscatorArgs,
                    $base64Encode
                );
            }
            if ($plainAssetsContent) {
                $mergedContent .= $this->filterContent(
                    $plainAssetsContent,
                    $type,
                    $uglifyJsEnabled,
                    false,
                    $uglifyJsArgs,
                    $jsObfuscatorArgs,
                    $base64Encode
                );
            }
            $mergedContent = $this->addLicenseStamp($mergedContent);
            foreach (glob($this->assetsCachePath . '/' . $mask) as $file) {
                unlink($file);
            }
            $cache->set($hash, $mergedContent);
            // static symlink
            $linkPath = $this->assetsCachePath . '/' . $link;
            if (file_exists($linkPath)) {
                unlink($linkPath);
            }
            copy($this->assetsCachePath . '/' . $hash, $linkPath);
        }
        return $link;
    }

    /**
     * @param string|array $asset
     * @param bool $cached
     *
     * @return string
     * @throws AssetGrinderException
     */
    public function getAssetContents($asset, bool $cached = true): string
    {
        $contents = '';
        if (is_array($asset)) {
            $contents .= $asset[0];
        } else if (is_string($asset)) {
            $fileName = ($cached ? $this->assetsCachePath : $this->assetsPath) . '/' . $asset;
            $data = @file_get_contents($fileName);
            if ($data === false) {
                throw new AssetGrinderException('No asset found: ' . $fileName);
            }
            $contents .= $data;
        }
        return $contents;
    }

    /**
     * @param string $asset
     * @param bool $returnStaticUrl
     * @param string|null $customOrigin
     *
     * @return string
     */
    public function getAssetUrl(string $asset, bool $returnStaticUrl = true, string $customOrigin = null): string
    {
        if ($returnStaticUrl) {
            $asset .= $this->customUrlPostfix;
        } else {
            $hash = @md5_file($this->assetsCachePath . '/' . $asset);
            $asset .= $this->customUrlPostfix;
            $asset .= strpos($asset, '?') === false ? '?' : '&';
            $asset .= 'dummy=' . $hash;
        }
        $relativePublicPath = removePrefix(
            $this->assetsCachePath,
            $this->webServerRoot
        );
        $customOrigin = $customOrigin ?: $this->webServerOrigin;
        return $customOrigin . $relativePublicPath . '/' . $asset;
    }

    /**
     * @param string $asset
     * @param string|array $type
     * @param bool $asyncJs
     *
     * @return string
     * @throws RandomException
     */
    public function getAssetTag($asset, string $type = self::TYPE_JS, bool $asyncJs = false): string
    {
        $assetTag = '';
        switch ($type) {
            case self::TYPE_CSS:
                if (is_string($asset)) {
                    $assetTag =
                        '<link ' . $this->customTagAttr . ' type="text/css" rel="stylesheet" href="' . $asset . '">';
                } else if (is_array($asset)) {
                    $assetTag =
                        '<style ' . $this->customTagAttr . '>' . $asset[0] . '</style>';
                }
                break;
            case self::TYPE_JS:
                $nonce = htmlspecialchars(base64_encode(random_bytes(16)), ENT_QUOTES, 'UTF-8');
                if (is_string($asset)) {
                    $assetTag =
                        '<script nonce="' . $nonce . '" ' . ($asyncJs ? 'async ' : '') . $this->customTagAttr . ' type="text/javascript" src="' . $asset . '"></script>';
                } else if (is_array($asset)) {
                    $assetTag =
                        '<script nonce="' . $nonce . '" ' . ($asyncJs ? 'async ' : '') . $this->customTagAttr . ' type="text/javascript">' . $asset[0] . '</script>';
                }
                break;
        }
        return $assetTag  . "\n";
    }

    /**
     * @param string $assetContent
     * @param string $type
     * @param bool $uglifyJsEnabled
     * @param bool $jsObfuscatorEnabled
     * @param string|null $uglifyJsArgs
     * @param string|null $jsObfuscatorArgs
     * @param bool $base64Encode
     * @param bool $removeWhiteSpaces
     *
     * @return string
     * @throws AssetGrinderException
     */
    public function filterContent(
        string $assetContent,
        string $type,
        ?bool $uglifyJsEnabled = null,
        ?bool $jsObfuscatorEnabled = null,
        ?string $uglifyJsArgs = null,
        ?string $jsObfuscatorArgs = null,
        ?bool $base64Encode = true,
        ?bool $removeWhiteSpaces = false
    ): string {
        $Asset = new StringAsset($assetContent);
        $Asset->setContent($assetContent);
        if (! $this->debugMode) {
            $filters = [];
            switch ($type) {
                case self::TYPE_CSS:
                    break;
                case self::TYPE_JS:
                    $filters = [new JsAssetFilter(
                        $uglifyJsEnabled ? $this->uglifyJsPath : null,
                        $jsObfuscatorEnabled ? $this->jsObfuscatorPath : null,
                        $uglifyJsArgs ?: $this->uglifyJsArgs,
                        $jsObfuscatorArgs ?: $this->jsObfuscatorArgs,
                        $base64Encode ?? $this->base64Encode,
                        $removeWhiteSpaces
                    )];
                    break;
            }
            foreach ($filters as $filter) {
                $filter->filterDump($Asset);
            }
        }
        return $Asset->getContent() ?: '';
    }

    /**
     * @param string $assetContent
     *
     * @return ?string
     */
    private function addLicenseStamp(string $mergedContent): ?string
    {
        if ($this->licenseStamp && $mergedContent) {
            return $this->licenseStamp . "\n\n" . $mergedContent;
        }
        return $mergedContent;
    }

    /**
     * @param string $asset
     * @param ?bool $obfuscate
     *
     * @return string
     */
    private function getAssetFileName(string $asset, ?bool &$obfuscate = null): string
    {
        $filename = basename($asset);
        $obfuscate = strpos($filename, '@') === false;
        if (!$obfuscate) {
            $dir = dirname($asset);
            $newFilename = str_replace('@', '', $filename);
            return ($dir === '.' || $dir === '/') ?
                $newFilename :
                $dir . DIRECTORY_SEPARATOR . $newFilename
            ;
        }
        return $asset;
    }
}
