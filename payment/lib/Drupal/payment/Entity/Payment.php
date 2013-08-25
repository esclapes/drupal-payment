<?php

/**
 * @file
 * Definition of Drupal\payment\Entity\Payment.
 */

namespace Drupal\payment\Entity;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Entity\EntityNG;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\Plugin\payment\context\PaymentContextInterface;
use Drupal\payment\Plugin\payment\line_item\PaymentLineItemInterface;
use Drupal\payment\Plugin\payment\status\PaymentStatusInterface;

/**
 * Defines a payment entity.
 *
 * @EntityType(
 *   base_table = "payment",
 *   bundle_label = @Translation("Payment context"),
 *   controllers = {
 *     "access" = "Drupal\payment\Entity\PaymentAccessController",
 *     "storage" = "Drupal\payment\Entity\PaymentStorageController",
 *   },
 *   entity_keys = {
 *     "bundle" = "bundle",
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   fieldable = TRUE,
 *   id = "payment",
 *   label = @Translation("Payment"),
 *   module = "payment",
 *   route_base_path = "admin/config/services/payment/context/{bundle}"
 * )
 */
class Payment extends EntityNG implements PaymentInterface {

  /**
   * The payment context.
   *
   * @var \Drupal\payment\Plugin\payment\context\PaymentContextInterface
   */
  protected $context;

  /**
   * Line items.
   *
   * @var array
   *   Keys are line item machine names. Values are
   *   \Drupal\payment\Plugin\payment\line_item\PaymentLineItemInterface
   *   objects.
   */
  protected $lineItems = array();

  /**
   * Payment statuses.
   *
   * @var array
   *   Values are \Drupal\payment\Plugin\payment\status\PaymentStatusInterface
   *   objects.
   */
  protected $statuses = array();

  /**
   * Overrides Entity::__construct().
   */
  public function __construct(array $values, $entity_type, $bundle = FALSE, $translations = array()) {
    parent::__construct($values, $entity_type, $bundle, $translations);
    $this->context = \Drupal::service('plugin.manager.payment.context')->createInstance($this->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function label($langcode = NULL) {
    return $this->getPaymentContext()->paymentDescription($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentContext() {
    return $this->context;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrencyCode($currency_code) {
    $this->currencyCode[0]->setValue($currency_code);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrencyCode() {
    return $this->currencyCode[0]->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLineItems(array $line_items) {
    foreach ($line_items as $line_item) {
      $this->setLineItem($line_item);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLineItem(PaymentLineItemInterface $line_item) {
    $line_item->setPaymentId($this->id());
    $this->lineItems[$line_item->getName()] = $line_item;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLineItems() {
    return $this->lineItems;
  }

  /**
   * {@inheritdoc}
   */
  public function getLineItem($name) {
    return isset($this->lineItems[$name]) ? $this->lineItems[$name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLineItemsByType($plugin_id) {
    $line_items = array();
    foreach ($this->getLineItems() as $line_item) {
      if ($line_item->getPluginId() == $plugin_id) {
        $line_items[$line_item->getName()] = $line_item;
      }
    }

    return $line_items;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatuses(array $statuses) {
    foreach ($statuses as $status) {
      $this->setStatus($status, FALSE);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(PaymentStatusInterface $status, $notify = TRUE) {
    $previousStatus = $this->getStatus();
    $status->setPaymentId($this->id());
    // Prevent duplicate statuses.
    if (!$this->getStatus() || $this->getStatus()->getPluginId() != $status->getPluginId()) {
      $this->statuses[] = $status;
    }
    if ($notify) {
      $handler = \Drupal::moduleHandler();
      foreach ($handler->getImplementations('payment_status_set') as $moduleName) {
        $handler->invoke($moduleName, 'payment_status_set', $this, $previousStatus);
        // If a hook invocation has added another log item, a new loop with
        // invocations has already been executed and we don't need to continue
        // with this one.
        if ($this->getStatus()->getPluginId() != $status->getPluginId()) {
          return;
        }
      }
      // @todo Invoke Rules event.
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatuses() {
    return $this->statuses;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return end($this->statuses);
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethodId($id) {
    $this->paymentMethodId[0]->setValue($id);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodId() {
    return $this->paymentMethodId[0]->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethod() {
    return entity_load('payment_method', $this->getPaymentMethodId());
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethodBrand($brand_name) {
    $this->paymentMethodBrand[0]->value = $brand_name;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodBrand() {
    return $this->paymentMethodBrand[0]->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($id) {
    $this->ownerId[0]->setValue($id);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->ownerId[0]->get('target_id')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->ownerId[0]->get('entity')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getAmount() {
    $total = 0;
    foreach ($this->getLineItems() as $line_item) {
      $total += $line_item->getTotalAmount();
    }

    return $total;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $handler = \Drupal::moduleHandler();
    $manager = \Drupal::service('plugin.manager.payment.status');
    // Preprocess the payment.
    $handler->invokeAll('payment_pre_execute', $this);
    // @todo Invoke Rules event.
    // Execute the payment.
    if (count($this->validate())) {
      $this->setStatus($manager->createInstance('payment_failed'));
    }
    else {
      $this->setStatus($manager->createInstance('payment_pending'));
      $this->getPaymentMethod()->executePaymentOperation($this, 'execute', $this->getPaymentMethodBrand());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageControllerInterface $storage_controller, array &$values) {
    // @todo Remove access to global $user once https://drupal.org/node/2032553
    //has been fixed.
    global $user;

    $values += array(
      'ownerId' => (int) $user->id(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageControllerInterface $controller, $update = TRUE) {
    $controller->saveLineItems(array(
      $this->id() => $this->getLineItems(),
    ));
    $controller->savePaymentStatuses(array(
      $this->id() => $this->getStatuses(),
  ));
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageControllerInterface $controller, array $entities) {
    $controller->deleteLineItems(array_keys($entities));
    $controller->deletePaymentStatuses(array_keys($entities));
  }
}