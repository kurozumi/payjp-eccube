<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Eccube\Application;
use Symfony\Component\Yaml\Yaml;

class Version20160417000100 extends AbstractMigration
{
    protected $entities = array(
        'Plugin\PayJp\Entity\PayJpConfig',
        'Plugin\PayJp\Entity\PayJpCustomer',
        'Plugin\PayJp\Entity\PayJpLog',
        'Plugin\PayJp\Entity\PayJpOrder',
        'Plugin\PayJp\Entity\PayJpToken',
    );
    
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs

        $app = Application::getInstance();
        $meta = $this->getMetadata($app['orm.em']);
        $tool = new SchemaTool($app['orm.em']);
        $tool->createSchema($meta);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

        $app = Application::getInstance();
        $meta = $this->getMetadata($app['orm.em']);

        $tool = new SchemaTool($app['orm.em']);
        $schemaFromMetadata = $tool->getSchemaFromMetadata($meta);
        
        $this->deleteFromDtbPayment();

        // テーブル削除
        foreach ($schemaFromMetadata->getTables() as $table) {
            if ($schema->hasTable($table->getName())) {
                $schema->dropTable($table->getName());
            }
        }

        // シーケンス削除
        foreach ($schemaFromMetadata->getSequences() as $sequence) {
            if ($schema->hasSequence($sequence->getName())) {
                $schema->dropSequence($sequence->getName());
            }
        }
    }
    
    public function postUp(Schema $schema)
    {
        $app = new \Eccube\Application();
        $app->initialize();
        $app->initializePlugin();
        $app->boot();

        $datetime = date('Y-m-d H:i:s');

        // DBタイプ取得
        $config_file = __DIR__ . '/../../../../../config/eccube/database.yml';
        $config = Yaml::parse(file_get_contents($config_file));

        // rank取得
        $select = "SELECT max(rank)+1 FROM dtb_payment";
        $rank = $this->connection->fetchColumn($select);

        // 支払い方法「PAY.JPクレジットカード決済」追加
        $payment_id = '';
        if ($config['database']['driver'] == 'pdo_mysql') {
            $insert = "INSERT INTO dtb_payment(creator_id, payment_method, charge, rule_max, rank, create_date, update_date, rule_min)
                        VALUES (1, 'PAY.JPクレジットカード決済', 0, null, $rank, '$datetime', '$datetime', null);";
            $this->connection->executeUpdate($insert);

            // 「PAY.JPクレジットカード決済」のpayment_id取得
            $select = "SELECT max(payment_id) FROM dtb_payment WHERE payment_method = 'PAY.JPクレジットカード決済'";
            $payment_id = $this->connection->fetchColumn($select);
        } else {
            $nextval = "SELECT nextval('dtb_payment_payment_id_seq')";
            $payment_id = $this->connection->fetchColumn($nextval);
            $insert = "INSERT INTO dtb_payment(payment_id, creator_id, payment_method, charge, rule_max, rank, create_date, update_date, rule_min)
                        VALUES ($payment_id, 1, 'PAY.JPクレジットカード決済', 0, null, $rank, '$datetime', '$datetime', 0);";
            $this->connection->executeUpdate($insert);
        }

        // プラグイン情報初期セット
        $insert = "INSERT INTO plg_pay_jp_config(id, api_key_secret, payment_id, created_at)
                    VALUES (1, 'YOUR_SECRET_KEY', $payment_id, '$datetime');";
        $this->connection->executeUpdate($insert);
    }
    
    public function deleteFromDtbPayment()
    {
        // 「PAY.JPクレジットカード決済」のpayment_idを取得
        $select = "SELECT payment_id FROM plg_pay_jp_config";
        $payment_id = $this->connection->fetchColumn($select);

        $update = "UPDATE dtb_payment SET del_flg = 1 WHERE payment_id = $payment_id";
        $this->connection->executeUpdate($update);

        $table = "dtb_payment_option";
        $where = array("payment_id" => $payment_id);
        $this->connection->delete($table, $where);
    }

    /**
     * @param EntityManager $em
     * @return array
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     */
    protected function getMetadata(EntityManager $em)
    {
        $meta = array();
        foreach ($this->entities as $entity) {
            $meta[] = $em->getMetadataFactory()->getMetadataFor($entity);
        }

        return $meta;
    }
}
