<?php

namespace weareferal\assetversioner;

use weareferal\assetversioner\services\Scan as ScanService;
use weareferal\assetversioner\services\KeyStore as KeyStoreService;
use weareferal\assetversioner\models\Settings;
use weareferal\assetversioner\twigextensions\AssetVersionerTwigExtensions;
use weareferal\assetversioner\events\FilesVersionedEvent;
use weareferal\assetversioner\utilities\assetutilities\versionAsset;
use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\services\AssetTransforms;
use craft\events\PluginEvent;
use craft\events\AssetTransformEvent;
use craft\events\ModelEvent;
use craft\console\Application as ConsoleApplication;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\elements\Asset;
use craft\records\Asset as AssetRecord;

use yii\base\Event;


class AssetVersioner extends Plugin
{
    public static $plugin;
    public $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Add in our console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'weareferal\assetversioner\console\controllers';
        }

        $this->setComponents([
            'scan' => ScanService::class,
            'keystore' => KeyStoreService::create()
        ]);

        Craft::$app->view->registerTwigExtension(new AssetVersionerTwigExtensions());

        Event::on(
            ScanService::class,
            ScanService::EVENT_AFTER_FILES_VERSIONED,
            function (FilesVersionedEvent $event) {
                $this->keystore->update($event->versioned_files);
            }
        );

        // Asset hashing
        Event::on(
            Asset::class,
            Asset::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                if ($this->settings->assetVersioningEnabled) {
                    $asset = $event->sender;
                    $extensions = AssetVersioner::getInstance()->getSettings()->assetVersioningExtensions;
                    $extensions = explode(",", $extensions);
                        
                    $pathinfo = pathinfo($asset->filename);
                    $extension = $pathinfo['extension'];
                    $filename = $pathinfo['filename'];
                    
                    if (in_array($extension, $extensions)) {
                        // Hash the asset
                        $file = stream_get_contents($asset->stream);
                        $hash = md5($file);
                        $path = $filename . '.' . $hash . '.' . $extension;

                        // Save new filename
                        $volume = $asset->getVolume();
                        $volume->renameFile($asset->path, $path);
                        $asset->filename = $path;
                        $record = AssetRecord::findOne($asset->id);
                        $record->filename = $path;
                        $record->save(false);
                    } else {
                        Craft::info("Skipping asset: " . $filename, "env-sync");
                    }
                }
            }
        );
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'asset-versioner/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
