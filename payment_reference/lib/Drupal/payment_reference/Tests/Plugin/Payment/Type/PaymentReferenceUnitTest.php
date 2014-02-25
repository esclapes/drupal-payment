<?php

/**
 * @file
 * Contains
 * \Drupal\payment_reference\Test\Plugin\Payment\Type\PaymentReferenceUnitTest.
 */

namespace Drupal\payment_reference\Test\Plugin\Payment\Type;

use Drupal\payment_reference\Plugin\Payment\Type\PaymentReference;
use Drupal\Tests\UnitTestCase;

/**
 * Tests \Drupal\payment_reference\Plugin\Payment\Type\PaymentReference.
 */
class PaymentReferenceUnitTest extends UnitTestCase {

  /**
   * The field instance config used for testing.
   *
   * @var \Drupal\field\Entity\FieldInstanceConfigConfig|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $fieldInstanceConfig;

  /**
   * The module handler used for testing.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The payment used for testing.
   *
   * @var \Drupal\payment\Entity\PaymentInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The payment type plugin under test.
   *
   * @var \Drupal\payment_reference\Plugin\Payment\Type\PaymentReference
   */
  protected $paymentType;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'description' => '',
      'group' => 'Payment Reference Field',
      'name' => '\Drupal\payment_reference\Plugin\Payment\Type\PaymentReference unit test',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $url_generator = $this->getMockBuilder('\Drupal\Core\Routing\UrlGenerator')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->any())
      ->method('generateFromRoute')
      ->will($this->returnValue('http://example.com'));

    $this->fieldInstanceConfig = $this->getMockBuilder('\Drupal\field\Entity\FieldInstanceConfig')
      ->disableOriginalConstructor()
      ->getMock();
    $this->fieldInstanceConfig->expects($this->any())
      ->method('label')
      ->will($this->returnValue($this->randomName()));

    $field_instance_config_storage = $this->getMockBuilder('\Drupal\field\FieldInstanceConfigStorageController')
      ->disableOriginalConstructor()
      ->getMock();
    $field_instance_config_storage->expects($this->any())
      ->method('load')
      ->will($this->returnValue($this->fieldInstanceConfig));

    $this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandler');

    $http_kernel = $this->getMockBuilder('\Drupal\Core\HttpKernel')
      ->disableOriginalConstructor()
      ->getMock();

    $request = $this->getMockBuilder('\Symfony\Component\HttpFoundation\Request')
      ->disableOriginalConstructor()
      ->getMock();

    $this->paymentType = new PaymentReference(array(), 'payment_reference', array(), $http_kernel, $request, $this->moduleHandler, $url_generator, $field_instance_config_storage);

    $this->payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $this->paymentType->setPayment($this->payment);
  }

  /**
   * Tests getFieldInstanceConfigId().
   */
  public function testGetFieldInstanceConfigId() {
    $this->payment->expects($this->once())
      ->method('get');
    $this->paymentType->getFieldInstanceConfigId();
  }

  /**
   * Tests setFieldInstanceConfigId().
   */
  public function testSetFieldInstanceConfigId() {
    $map = array(array('payment_reference_field_instance', $this->paymentType));
    $this->payment->expects($this->once())
      ->method('set')
      ->will($this->returnValueMap($map));
    $this->paymentType->setFieldInstanceConfigId($this->randomName());
  }

  /**
   * Tests paymentDescription().
   */
  public function testPaymentDescription() {
    $this->assertSame($this->paymentType->paymentDescription(), $this->fieldInstanceConfig->label());
  }

}
