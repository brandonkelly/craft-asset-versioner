<?php
namespace weareferal\assetversioner\console\controllers;

use weareferal\assetversioner\AssetVersioner;

use Craft;
use yii\console\Controller;
use yii\helpers\Console;


class ScanController extends Controller
{
    public function actionIndex()
    {
        if (!AssetVersioner::getInstance()->getSettings()->staticVersioningEnabled) {
            $this->stdout('Static file versioning is not enabled. Please enable via either a config setting or via the Control Panel settings' . PHP_EOL, Console::FG_RED, Console::UNDERLINE);
            return 0;
        }

        $result = AssetVersioner::getInstance()->scan->scan();

        $this->stdout('Files:' . PHP_EOL, Console::UNDERLINE);
        foreach($result["files"] as $file) {
            $this->stdout($file . PHP_EOL);
        }
        $this->stdout(PHP_EOL);

        if (array_key_exists("deleted_files", $result)) {
            $this->stdout('Deleted Files:' . PHP_EOL, Console::FG_RED, Console::UNDERLINE);
            foreach($result["deleted_files"] as $file) {
                $this->stdout($file . PHP_EOL);
            }
            $this->stdout(PHP_EOL);
        }

        $this->stdout('Generated Versions:' . PHP_EOL, Console::FG_GREEN, Console::UNDERLINE);
        foreach($result["versioned_files"] as $file) {
            $this->stdout($file . PHP_EOL);
        }
    }
}
