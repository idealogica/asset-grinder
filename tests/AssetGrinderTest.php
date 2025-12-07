<?php

use Idealogica\AssetGrinder\AssetHandler;
use PHPUnit\Framework\TestCase;

/**
 * Class OrmHelperTest
 */
class AssetGrinderTest extends TestCase
{
    const WEB_SERVER_ORIGIN = 'https://www.test.tld';

    const WEB_SERVER_ROOT = __DIR__ . '/public';

    const ASSETS_PATH = __DIR__ . '/assets';

    const PUBLIC_PATH = __DIR__ . '/public/assets';

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();
        $this->clearPublicDir();
    }

    /**
     *
     */
    public function tearDown()
    {
        parent::tearDown();
        $this->clearPublicDir();
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetContentsDebug()
    {
        $contents = $this
            ->createAssetHandler(null, null, null, null, true, null, true)
            ->buildAssetContents('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, false);
        $contents = $this->filterAsset($contents);
        self::assertEquals('var v1 = true; console.log(v1); var v2 = false; console.log(v2);', $contents);
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetContentsProd()
    {
        $contents = $this
            ->createAssetHandler(null, null, null, null, true, null, false)
            ->buildAssetContents('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, false);
        $contents = $this->filterAsset($contents);
        self::assertRegExp('#' . preg_quote("(new Function(new TextDecoder('utf-8')") . '#i', $contents);
        self::assertRegExp(
            '#' . preg_quote("map(b => parseInt(b, 16))") . '#i',
            $contents
        );
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetContentsNoBase64()
    {
        $contents = $this
            ->createAssetHandler(null, null, null, null, false, null, false)
            ->buildAssetContents('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, false);
        $contents = $this->filterAsset($contents);
        self::assertRegExp('#var _0x#i', $contents);
        // self::assertRegExp('#\(v2\);$#i', $contents);
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetContentsNoObfuscation()
    {
        $contents = $this
            ->createAssetHandler(null, false, null, null, false, null, false)
            ->buildAssetContents('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, false);
        $contents = $this->filterAsset($contents);
        self::assertRegExp(
            '#' . preg_quote("var v1=!0,v2=(console.log(v1),!1);console.log(v2);") . '#i',
            $contents
        );
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetContentsNoMinification()
    {
        $contents = $this
            ->createAssetHandler(false, false, null, null, false, null, false)
            ->buildAssetContents('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, false);
        $contents = $this->filterAsset($contents);
        self::assertRegExp(
            '#' . preg_quote("var v1 = true; console.log(v1); var v2 = false; console.log(v2);") . '#i',
            $contents
        );
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetTagStatic()
    {
        $contents = $this
            ->createAssetHandler(null, null, null, null, true, null, false)
            ->buildAssetTag('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, true, false);
        $contents = $this->filterAsset($contents);
        self::assertRegExp(
            '#<script nonce="[^"]+" ' . preg_quote('data-attr="true" type="text/javascript" src="https://www.test.tld/assets/__cpa.pack.js?postfix=true"></script>') . '#i',
            $contents
        );
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetTagDynamic()
    {
        $contents = $this
            ->createAssetHandler(null, null, null, null, true, null, false)
            ->buildAssetTag('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, false, false);
        $contents = $this->filterAsset($contents);
        self::assertRegExp(
            '#<script nonce="[^"]+" ' . preg_quote('data-attr="true" type="text/javascript" src="https://www.test.tld/assets/__cpa.pack.js?postfix=true&dummy=') . '#i',
            $contents
        );
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetTagDynamicNoPostfix()
    {
        $contents = $this
            ->createAssetHandler(null, null, null, null, true, '', false)
            ->buildAssetTag('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, false, false);
        $contents = $this->filterAsset($contents);
        self::assertRegExp(
            '#<script nonce="[^"]+" ' . preg_quote('data-attr="true" type="text/javascript" src="https://www.test.tld/assets/__cpa.pack.js?dummy=') . '#i',
            $contents
        );
    }

    /**
     * @throws \Idealogica\AssetGrinder\Exception\AssetGrinderException
     */
    public function testBuildAssetUrl()
    {
        $contents = $this
            ->createAssetHandler(null, null, null, null, true, null, false)
            ->buildAssetUrl('pack', ['a1.js', 'a2.js'], AssetHandler::TYPE_JS, true, false);
        $contents = $this->filterAsset($contents);
        self::assertEquals(
            'https://www.test.tld/assets/__cpa.pack.js?postfix=true',
            $contents
        );
    }

    /**
     * @param string|null $uglifyJsPath
     * @param string|null $jsObfuscatorPath
     * @param string|null $uglifyJsArgs
     * @param string|null $jsObfuscatorArgs
     * @param bool $base64Encode
     * @param string|null $customUrlPostfix
     * @param bool $debugMode
     *
     * @return AssetHandler
     */
    protected function createAssetHandler(
        string $uglifyJsPath = null,
        string $jsObfuscatorPath = null,
        string $uglifyJsArgs = null,
        string $jsObfuscatorArgs = null,
        bool $base64Encode = true,
        string $customUrlPostfix = null,
        bool $debugMode = false
    ) {
        return new AssetHandler(
            self::WEB_SERVER_ORIGIN,
            self::WEB_SERVER_ROOT,
            self::ASSETS_PATH,
            self::PUBLIC_PATH,
            $uglifyJsPath ?? '/usr/bin/uglifyjs',
            $jsObfuscatorPath ?? '/usr/bin/javascript-obfuscator',
            $uglifyJsArgs,
            $jsObfuscatorArgs,
            $base64Encode,
            '__cpa',
            $customUrlPostfix ?? '?postfix=true',
            'data-attr="true"',
            'LICENSE',
            $debugMode
        );
    }

    /**
     * @param string $asset
     *
     * @return string
     */
    protected function filterAsset(string $asset): string
    {
        return trim(preg_replace('#\s+#', ' ', $asset));
    }

    /**
     * @return $this
     */
    protected function clearPublicDir()
    {
        foreach(glob(self::PUBLIC_PATH . '/*') as $file) {
            if ($file !== '.') {
                unlink($file);
            }
        }
        return $this;
    }
}
