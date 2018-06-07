<?php

namespace Plugin\PayJp;

use Eccube\Entity\PaymentOption;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Entity\Payment;
use Eccube\Repository\PaymentRepository;

class PluginManager extends AbstractPluginManager
{

    public function install($config, $app)
    {
        $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code']);

        // アセットを公開ディレクトリ以下にコピーする
        $this->deleteAssets();
        $this->copyAssets();
    }

    public function uninstall($config, $app)
    {
        $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code'], 0);
        $this->deleteAssets();
    }

    public function enable($config, $app)
    {

    }

    public function disable($config, $app)
    {

    }

    public function update($config, $app)
    {
        $this->deleteAssets();
        $this->copyAssets();
    }

    /**
     * アセットを削除する
     */
    private function deleteAssets() {
        $pub_image_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/html/plugin/pay_jp';
        if (file_exists($pub_image_dir)) {
            $dh = opendir($pub_image_dir);
            while (false !== ($entry = readdir($dh))) {
                if ($entry != "." && $entry != "..") {
                    unlink("$pub_image_dir/$entry");
                }
            }
            rmdir($pub_image_dir);
        }
    }

    /**
     * アセットを公開ディレクトリ以下にコピーする
     */
    private function copyAssets() {
        $pub_image_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/html/plugin/pay_jp';
        $resource_dir = dirname(__FILE__) . '/Resource';
        mkdir($pub_image_dir, 0755, true);
        copy("$resource_dir/js/pay_jp_admin.js", "$pub_image_dir/pay_jp_admin.js");
        copy("$resource_dir/css/pay_jp.css", "$pub_image_dir/pay_jp.css");
    }
}
