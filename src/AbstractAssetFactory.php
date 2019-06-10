<?php
namespace Idealogica\AssetGrinder;

/**
 * Class AbstractAssetFactory
 * @package Idealogica\AssetGrinder
 */
class AbstractAssetFactory
{
    /**
     * @var null|AssetHandler
     */
    protected $assetHandler = null;

    /**
     * AbstractAssetFactory constructor.
     *
     * @param AssetHandler $assetHandler
     */
    public function __construct(AssetHandler $assetHandler)
    {
        $this->assetHandler = $assetHandler;
    }
}
