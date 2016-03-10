<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Entity\Payment\PaymentStatusFormTest.
 */

namespace Drupal\Tests\payment\Unit\Entity\Payment;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\payment\Entity\Payment\PaymentStatusForm;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodUpdatePaymentStatusInterface;
use Drupal\payment\Plugin\Payment\Status\PaymentStatusInterface;
use Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface;
use Drupal\plugin\PluginType\PluginType;
use Drupal\plugin\PluginType\PluginTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\payment\Entity\Payment\PaymentStatusForm
 *
 * @group Payment
 */
class PaymentStatusFormTest extends UnitTestCase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The payment.
   *
   * @var \Drupal\payment\Entity\Payment|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The payment status plugin manager.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentStatusManager;

  /**
   * The plugin selector.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pluginSelector;

  /**
   * The plugin selector manager.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pluginSelectorManager;

  /**
   * The plugin type manager.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pluginTypeManager;

  /**
   * The typed config manager
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $typedConfigManager;

  /**
   * The string translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * The class under test.
   *
   * @var \Drupal\payment\Entity\Payment\PaymentStatusForm
   */
  protected $sut;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->currentUser = $this->getMock(AccountInterface::class);

    $this->paymentStatusManager = $this->getmock(PaymentStatusManagerInterface::class);

    $this->pluginSelector = $this->getMock(PluginSelectorInterface::class);

    $this->pluginSelectorManager = $this->getMock(PluginSelectorManagerInterface::class);

    $this->stringTranslation = $this->getStringTranslationStub();

    $class_resolver = $this->getMock(ClassResolverInterface::class);

    $this->pluginTypeManager = $this->getmock(PluginTypeManagerInterface::class);

    $plugin_type_definition = [
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
      'provider' => $this->randomMachineName(),
    ];

    $this->typedConfigManager = $this->getMock(TypedConfigManagerInterface::class);

    $this->typedConfigManager->expects($this->once())
      ->method('hasConfigSchema')
      ->willReturn(true);

    $plugin_type = new PluginType($plugin_type_definition, $this->stringTranslation, $class_resolver, $this->paymentStatusManager, $this->typedConfigManager);

    $this->pluginTypeManager->expects($this->any())
      ->method('getPluginType')
      ->with('payment_method')
      ->willReturn($plugin_type);

    $this->urlGenerator = $this->getmock(UrlGeneratorInterface::class);

    $this->payment = $this->getMock(PaymentInterface::class);

    $this->sut = new PaymentStatusForm($this->currentUser, $this->urlGenerator, $this->stringTranslation, $this->pluginSelectorManager, $this->pluginTypeManager);
    $this->sut->setEntity($this->payment);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   */
  function testCreate() {
    $container = $this->getMock(ContainerInterface::class);
    \Drupal::setContainer($container);
    $map = array(
      array('current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser),
      array('plugin.manager.plugin.plugin_selector', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->pluginSelectorManager),
      array('plugin.plugin_type_manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->pluginTypeManager),
      array('string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation),
      array('url_generator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->urlGenerator),
    );
    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    $form = PaymentStatusForm::create($container);
    $this->assertInstanceOf(PaymentStatusForm::class, $form);
  }

  /**
   * @covers ::form
   * @covers ::getPluginSelector
   */
  public function testForm() {
    $form = [];
    $form_state = new FormState();

    $settable_payment_status_ids = array($this->randomMachineName());

    $language = $this->getMock(LanguageInterface::class);

    $payment_method = $this->getMock(PaymentStatusFormUnitTestDummyPaymentMethodUpdateStatusInterface::class);
    $payment_method->expects($this->once())
      ->method('getSettablePaymentStatuses')
      ->with($this->currentUser)
      ->willReturn($settable_payment_status_ids);

    $this->payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->willReturn($payment_method);
    $this->payment->expects($this->any())
      ->method('language')
      ->willReturn($language);

    $this->pluginSelectorManager->expects($this->once())
      ->method('createInstance')
      ->willReturn($this->pluginSelector);

    $plugin_selector_form = [
      'foo' => $this->randomMachineName(),
    ];

    $this->pluginSelector->expects($this->atLeastOnce())
      ->method('buildSelectorForm')
      ->with([], $form_state)
      ->willReturn($plugin_selector_form);

    $build = $this->sut->form($form, $form_state);
    $this->assertInternalType('array', $build);
    $this->assertArrayHasKey('payment_status', $build);
    $this->assertSame($plugin_selector_form, $build['payment_status']);

    // Build the form again to make sure the plugin selector is only created
    // once.
    $this->sut->form($form, $form_state);
  }

  /**
   * @covers ::validateForm
   * @covers ::getPluginSelector
   */
  public function testValidateForm() {
    $form = [
      'payment_status' => [
        'foo' => $this->randomMachineName(),
      ],
    ];
    $form_state = new FormState();

    $this->pluginSelectorManager->expects($this->once())
      ->method('createInstance')
      ->willReturn($this->pluginSelector);

    $this->pluginSelector->expects($this->atLeastOnce())
      ->method('validateSelectorForm')
      ->with($form['payment_status'], $form_state);

    $this->sut->validateForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   * @covers ::getPluginSelector
   */
  public function testSubmitForm() {
    $form = [
      'payment_status' => [
        'foo' => $this->randomMachineName(),
      ],
    ];
    $form_state = new FormState();

    $payment_status = $this->getMock(PaymentStatusInterface::class);

    $this->pluginSelectorManager->expects($this->once())
      ->method('createInstance')
      ->willReturn($this->pluginSelector);

    $this->pluginSelector->expects($this->atLeastOnce())
      ->method('getSelectedPlugin')
      ->willReturn($payment_status);
    $this->pluginSelector->expects($this->atLeastOnce())
      ->method('submitSelectorForm')
      ->with($form['payment_status'], $form_state);

    $url = new Url($this->randomMachineName());

    $this->payment->expects($this->once())
      ->method('setPaymentStatus')
      ->with($payment_status);
    $this->payment->expects($this->once())
      ->method('save');
    $this->payment->expects($this->once())
      ->method('urlInfo')
      ->with('canonical')
      ->willReturn($url);

    $this->sut->submitForm($form, $form_state);
    $this->assertSame($url, $form_state->getRedirect());
  }

  /**
   * @covers ::actions
   */
  public function testActions() {
    $form = [];
    $form_state = $this->getMock(FormStateInterface::class);

    $method = new \ReflectionMethod($this->sut, 'actions');
    $method->setAccessible(TRUE);
    $method->invokeArgs($this->sut, array($form, $form_state));
  }

}

/**
 * Extends two interfaces, because we can only mock one.
 */
interface PaymentStatusFormUnitTestDummyPaymentMethodUpdateStatusInterface extends PaymentMethodUpdatePaymentStatusInterface, PaymentMethodInterface {
}
