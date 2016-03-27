<?php

/**
 * @file
 * Contains \Drupal\payment\Entity\Payment\PaymentStatusForm.
 */

namespace Drupal\payment\Entity\Payment;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodUpdatePaymentStatusInterface;
use Drupal\payment\Plugin\Payment\PaymentAwarePluginManagerDecorator;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface;
use Drupal\plugin\PluginDiscovery\LimitedPluginDiscoveryDecorator;
use Drupal\plugin\PluginType\PluginTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment status update form.
 */
class PaymentStatusForm extends EntityForm {

  /**
   * The plugin selector manager.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface
   */
  protected $pluginSelectorManager;

  /**
   * The plugin type manager.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeManagerInterface
   */
  protected $pluginTypeManager;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Session\AccountInterface
   *   The current user.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *    The string translator.
   * @param \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface $plugin_selector_manager
   * @param \Drupal\plugin\PluginType\PluginTypeManagerInterface $plugin_type_manager
   *   The plugin type manager.
   */
  function __construct(AccountInterface $current_user, UrlGeneratorInterface $url_generator, TranslationInterface $string_translation, PluginSelectorManagerInterface $plugin_selector_manager, PluginTypeManagerInterface $plugin_type_manager) {
    $this->currentUser = $current_user;
    $this->pluginTypeManager = $plugin_type_manager;
    $this->pluginSelectorManager = $plugin_selector_manager;
    $this->stringTranslation = $string_translation;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('current_user'), $container->get('url_generator'), $container->get('string_translation'), $container->get('plugin.manager.plugin.plugin_selector'), $container->get('plugin.plugin_type_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['payment_status'] = $this->getPluginSelector($form_state)->buildSelectorForm([], $form_state);

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions = array($actions['submit']);

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->getPluginSelector($form_state)->validateSelectorForm($form['payment_status'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();
    $plugin_selector = $this->getPluginSelector($form_state);
    $plugin_selector->submitSelectorForm($form['payment_status'], $form_state);
    $payment->setPaymentStatus($plugin_selector->getSelectedPlugin());
    $payment->save();

    $form_state->setRedirectUrl($payment->urlInfo());
  }

  /**
   * Gets the plugin selector.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface
   */
  protected function getPluginSelector(FormStateInterface $form_state) {
    if ($form_state->has('plugin_selector')) {
      $plugin_selector = $form_state->get('plugin_selector');
    }
    else {
      /** @var \Drupal\payment\Entity\PaymentInterface $payment */
      $payment = $this->getEntity();

      $plugin_type = $this->pluginTypeManager->getPluginType('payment_status');

      $payment_method = $payment->getPaymentMethod();
      $payment_status_discovery = new LimitedPluginDiscoveryDecorator($plugin_type->getPluginManager());
      if ($payment_method instanceof PaymentMethodUpdatePaymentStatusInterface) {
        $payment_status_discovery->setDiscoveryLimit($payment_method->getSettablePaymentStatuses($this->currentUser, $payment));
      }
      $payment_status_manager = new PaymentAwarePluginManagerDecorator($payment, $plugin_type->getPluginManager(), $payment_status_discovery);

      $plugin_selector = $this->pluginSelectorManager->createInstance('payment_select_list');
      $plugin_selector->setSelectablePluginType($plugin_type, $payment_status_manager);
      $plugin_selector->setRequired();
      $plugin_selector->setLabel($this->t('Payment status'));

      $form_state->set('plugin_selector', $plugin_selector);
    }

    return $plugin_selector;
  }

}
