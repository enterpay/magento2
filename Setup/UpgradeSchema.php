<?php

namespace Solteq\Enterpay\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface{

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
      $installer = $setup;
      $installer->startSetup();

      if (version_compare($context->getVersion(), '2.0.1', '<')) {
        $this->addFeeTable($installer);
      }

      $installer->endSetup();
    }

    protected function addFeeTable($installer)
    {
      $table = $installer->getConnection()->newTable(
        $installer->getTable('enterpay_invoice_fee')
      )->addColumn(
        'entity_id',
        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
        null,
        ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
        'Entity Id'
      )->addColumn(
        'payment_id',
        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
        null,
        ['unsigned' => true, 'nullable' => false],
        'Payment Id'
      )->addColumn(
        'product_id',
        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
        255,
        [],
        'Product Id'
      )->addColumn(
        'description',
        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
        255,
        [],
        'Description'
      )->addColumn(
        'tax_percent',
        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
        '12,4',
        [],
        'Fee Tax Percent'
      )->addColumn(
        'base_amount',
        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
        '12,4',
        [],
        'Base Amount'
      )->addColumn(
        'base_amount_authorized',
        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
        '12,4',
        [],
        'Base Amount Authorized'
      )->addColumn(
        'base_amount_captured',
        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
        '12,4',
        [],
        'Base Amount Captured'
      )->addColumn(
        'base_amount_canceled',
        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
        '12,4',
        [],
        'Base Amount Canceled'
      )->addColumn(
        'base_amount_refunded',
        \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
        '12,4',
        [],
        'Base Amount Refunded'
      )->addIndex(
        $installer->getIdxName('enterpay_invoice_fee', ['payment_id']),
        ['payment_id']
      )->addForeignKey(
        $installer->getFkName('enterpay_invoice_fee', 'payment_id', 'sales_order_payment', 'entity_id'),
        'payment_id',
        $installer->getTable('sales_order_payment'),
        'entity_id',
        \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
      )->setComment(
        'Enterpay Invoice Fee'
      );

      $installer->getConnection()->createTable($table);
    }
}