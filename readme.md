# AssetGrinder - High level asset minification and obfuscation library

## 1. What is AssetGrinder?

AssetGrinder is a high level asset minification and obfuscation library. 
It supports caching and dynamical asset generation. 
AssetGrinder uses `uglifyjs` and `javascript-obfuscator` under the hood so you need to install them before use. 

## 2. Installation

```
composer require idealogica/asset-grinder:~1.0.0
```

## 3. Basic example

```
$assetHandler = new Idealogica\AssetGrinder\AssetHandler(
    ServerRequest::fromGlobals(),
    __DIR__ . '/assets',
    __DIR__ . '/public',
    $_SERVER['HOME'] . '/.npm_global/bin/uglifyjs',
    $_SERVER['HOME'] . '/.npm_global/bin/javascript-obfuscator'
);
$url = $assetHandler->buildAssetUrl('pack', ['asset1.js', 'asset2.js'], AssetHandler::TYPE_JS, false, false);
```

## 4. License

AssetGrinder is licensed under a [MIT License](https://opensource.org/licenses/MIT).
