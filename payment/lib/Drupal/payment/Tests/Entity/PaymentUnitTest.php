<?php

/**
 * @file
 * Contains class \Drupal\payment\Tests\Entity\PaymentUnitTest.
 */

namespace Drupal\payment\Tests\Entity;

use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\Entity\PaymentMethodInterface;
use Drupal\payment\Plugin\payment\Context\PaymentContextInterface;
use Drupal\payment\Generate;
use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests \Drupal\payment\Entity\Payment.
 */
class PaymentUnitTest extends DrupalUnitTestBase {

  /**
   * The payment bundle to test with.
   *
   * @var string
   */
  protected $bundle = 'payment_unavailable';

  /**
   * The payment line item manager.
   *
   * @var \Drupal\payment\Plugin\payment\line_item\Manager
   */
  protected $lineItemManager;

  /**
   * {@inheritdoc}
   */
  public static $modules = array('payment', 'system');

  /**
   * The payment to test with.
   *
   * @var \Drupal\payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * The payment status manager.
   *
   * @var \Drupal\payment\Plugin\payment\status\Manager
   */
  protected $statusManager;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'description' => '',
      'name' => '\Drupal\payment\Entity\Payment unit test',
      'group' => 'Payment',
    );
  }

  /**
   * {@inheritdoc
   */
  protected function setUp() {
    parent::setUp();
    $this->bundle = 'payment_unavailable';
    $this->lineItemManager = $this->container->get('plugin.manager.payment.line_item');
    $this->statusManager = $this->container->get('plugin.manager.payment.status');
    $this->payment = entity_create('payment', array(
      'bundle' => $this->bundle,
    ));
  }

  /**
   * Tests label().
   */
  protected function testLabel() {
    $this->assertIdentical($this->payment->label(), 'Unavailable');
  }

  /**
   * Tests bundle().
   */
  protected function testBundle() {
    $this->assertIdentical($this->payment->bundle(), $this->bundle);
  }

  /**
   * Tests getPaymentContext().
   */
  protected function testGetPaymentContext() {
    if ($this->assertTrue($this->payment->getPaymentContext() instanceof PaymentContextInterface)) {
      $this->assertIdentical($this->payment->getPaymentContext()->getPluginId(), $this->bundle);
    }
  }

  /**
   * Tests setCurrencyCode() and getCurrencyCode().
   */
  protected function testGetCurrencyCode() {
    $currency_code = 'ABC';
    $this->assertTrue($this->payment->setCurrencyCode($currency_code) instanceof PaymentInterface);
    $this->assertIdentical($this->payment->getCurrencyCode(), $currency_code);
  }

  /**
   * Tests setLineItem() and getLineItem().
   */
  protected function testGetLineItem() {
    $line_item = $this->lineItemManager->createInstance('payment_basic');
    $line_item->setName($this->randomName());
    $this->assertTrue($this->payment->setLineItem($line_item) instanceof PaymentInterface);
    $this->assertIdentical($this->payment->getLineItem($line_item->getName()), $line_item);
  }

  /**
   * Tests setLineItems() and getLineItems().
   */
  protected function testGetLineItems() {
    $line_item_1 = $this->lineItemManager->createInstance('payment_basic');
    $line_item_1->setName($this->randomName());
    $line_item_2 = $this->lineItemManager->createInstance('payment_basic');
    $line_item_2->setName($this->randomName());
    $this->assertTrue($this->payment->setLineItems(array($line_item_1, $line_item_2)) instanceof PaymentInterface);
    $this->assertIdentical($this->payment->getLineItems(), array(
      $line_item_1->getName() => $line_item_1,
      $line_item_2->getName() => $line_item_2,
    ));
  }

  /**
   * Tests getLineItemsByType().
   */
  protected function testGetLineItemsByType() {
    $type = 'payment_basic';
    $line_item = $this->lineItemManager->createInstance('basic');
    $this->assertTrue($this->payment->setLineItem($line_item) instanceof PaymentInterface);
    $this->assertEqual($this->payment->getLineItemsByType($type), array(
      $line_item->getName() => $line_item,
    ));
  }

  /**
   * Tests setStatus() and getStatus().
   */
  protected function testGetStatus() {
    $status = $this->statusManager->createInstance('payment_created');
    // @todo Test notifications.
    $this->assertTrue($this->payment->setStatus($status, FALSE) instanceof PaymentInterface);
    $this->assertEqual($this->payment->getStatus(), $status);
  }

  /**
   * Tests setStatuses() and getStatuses().
   */
  protected function testGetStatuses() {
    $statuses = array($this->statusManager->createInstance('payment_created'), $this->statusManager->createInstance('payment_pending'));
    $this->assertTrue($this->payment->setStatuses($statuses) instanceof PaymentInterface);
    $this->assertEqual($this->payment->getStatuses(), $statuses);
  }

  /**
   * Tests setPaymentMethodId() and getPaymentMethodId().
   */
  protected function testGetPaymentMethodId() {
    $id = 5;
    $this->assertTrue($this->payment->setPaymentMethodId($id) instanceof PaymentInterface);
    $this->assertIdentical($this->payment->getPaymentMethodId(), $id);
  }

  /**
   * Tests getPaymentMethod().
   */
  protected function testGetPaymentMethod() {
    $payment_method = Generate::createPaymentMethod(1);
    $payment_method->save();
    $this->payment->setPaymentMethodId($payment_method->id());
    $this->assertTrue($this->payment->getPaymentMethod() instanceof PaymentMethodInterface);
  }

  /**
   * Tests setPaymentMethodBrand() and getPaymentMethodBrand().
   */
  protected function testGetPaymentMethodBrand() {
    $brand_name = $this->randomName();
    $this->assertTrue($this->payment->setPaymentMethodBrand($brand_name) instanceof PaymentInterface);
    $this->assertIdentical($this->payment->getPaymentMethodBrand(), $brand_name);
  }

  /**
   * Tests setOwnerId() and getOwnerId().
   */
  protected function testGetOwnerId() {
    $id = 5;
    $this->assertTrue($this->payment->setOwnerId($id) instanceof PaymentInterface);
    $this->assertIdentical($this->payment->getOwnerId(), $id);
  }

  /**
   * Tests getAmount().
   */
  protected function testGetAmount() {
    $amount = 3;
    $quantity = 2;
    for ($i = 0; $i < 2; $i++) {
      $line_item = $this->lineItemManager->createInstance('payment_basic');
      $line_item->setName($this->randomName());
      $line_item->setAmount($amount);
      $line_item->setQuantity($quantity);
      $this->payment->setLineItem($line_item);
    }
    $this->assertIdentical($this->payment->getAmount(), 12);
  }
}