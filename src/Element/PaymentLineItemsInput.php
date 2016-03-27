<?php

/**
 * @file
 * Contains \Drupal\payment\Element\PaymentLineItemsInput.
 */

namespace Drupal\payment\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\currency\FormElementCallbackTrait;
use Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface;
use Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a payment line items configuration element.
 *
 * @FormElement("payment_line_items_input")
 *
 */
class PaymentLineItemsInput extends FormElement implements ContainerFactoryPluginInterface {

  use FormElementCallbackTrait;

  /**
   * An unlimited cardinality.
   */
  const CARDINALITY_UNLIMITED = -1;

  /**
   * The payment line item manager.
   *
   * @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManagerInterface
   */
  protected $paymentLineItemManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Creates a new instance.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManagerInterface $payment_line_item_manager
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, TranslationInterface $string_translation, RendererInterface $renderer, PaymentLineItemManagerInterface $payment_line_item_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->paymentLineItemManager = $payment_line_item_manager;
    $this->renderer = $renderer;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('string_translation'), $container->get('renderer'), $container->get('plugin.manager.payment.line_item'));
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $plugin_id = $this->getPluginId();

    return array(
      // The number of values this element allows, which must be at least as
      // many as the number of line items in the default value. For unlimited
      // values, use
      // \Drupal\payment\Element\PaymentLineItemsInput::CARDINALITY_UNLIMITED.
      '#cardinality' => self::CARDINALITY_UNLIMITED,
      // An array of
      // \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface
      // objects (required).
      '#default_value' => [],
      // A \Drupal\payment\Entity\PaymentInterface object (optional).
      '#payment' => NULL,
      '#process' => [[get_class($this), 'instantiate#process#' . $plugin_id]],
    );
  }

  /**
   * Implements form #process callback.
   */
  public function process(array $element, FormStateInterface $form_state, array $form) {
    $plugin_id = $this->getPluginId();

    // Set internal configuration.
    $element += array(
      '#value' => [],
    );
    $element['#payment_line_items'] = static::getLineItems($element, $form_state);
    $element['#element_validate'] = array(function(array &$element, FormStateInterface $form_state, array &$form) use ($plugin_id) {
      /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
      $element_info_manager = \Drupal::service('plugin.manager.element_info');
      /** @var \Drupal\payment\Element\PaymentLineItemsInput $element_plugin */
      $element_plugin = $element_info_manager->createInstance($plugin_id);
      $element_plugin->validate($element, $form_state, $form);
    });
    $element['#tree'] = TRUE;
    $element['#id'] = $this->getElementId($element, $form_state);
    $element['#theme_wrappers'] = array('container');

    // Validate the element configuration.
    if ($element['#cardinality'] != self::CARDINALITY_UNLIMITED && count($element['#default_value']) > $element['#cardinality']) {
      throw new \InvalidArgumentException('The number of default line items can not be higher than the cardinality.');
    }
    foreach ($element['#default_value'] as $line_item) {
      if (!($line_item instanceof PaymentLineItemInterface)) {
        throw new \InvalidArgumentException('A default line item does not implement \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface.');
      }
    }

    static::initializeLineItems($element, $form_state);
    $line_items = static::getLineItems($element, $form_state);

    // Build the line items.
    $line_items_prefix = implode('-', array_merge($element['#parents'], ['line_items']));
    $element['line_items'] = array(
      '#empty' => $this->t('There are no line items yet.'),
      '#header' => array(array(
        'data' => $this->t('Name'),
        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
      ), array(
        'data' => $this->t('Type'),
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ), $this->t('Configuration'), $this->t('Weight'), $this->t('Operations')),
      '#tabledrag' => array(array(
        'action' => 'order',
        'relationship' => 'self',
        'group' => 'payment-line-item-weight',
      )),
      '#type' => 'table',
      '#tree' => TRUE,
      '#id' => Html::getUniqueId($line_items_prefix)
    );

    foreach ($line_items as $delta => $line_item) {

      $element['line_items'][$line_item->getName()] = array(
        '#attributes' => array(
          'class' => array(
            'payment-line-item',
            'payment-line-item-name-' . $line_item->getName(),
            'payment-line-item-plugin-' . $line_item->getPluginId(),
          ),
        ),
      );
      $element['line_items'][$line_item->getName()]['#id'] = Html::getId(implode('-', [$line_items_prefix, $line_item->getName()]));
      $element['line_items'][$line_item->getName()]['name'] = array(
        '#markup' => $line_item->getName(),
      );
      $line_item_definition = $line_item->getPluginDefinition();
      $element['line_items'][$line_item->getName()]['type'] = array(
        '#markup' => $line_item_definition['label'],
      );
      $element['line_items'][$line_item->getName()]['plugin_form'] = $line_item->buildConfigurationForm([], $form_state);
      $element['line_items'][$line_item->getName()]['weight'] = array(
        '#attributes' => array(
          'class' => array('payment-line-item-weight'),
        ),
        '#default_value' => $delta,
        '#delta' => count($line_items),
        '#title' => $this->t('Weight'),
        '#type' => 'weight',
      );
      $element['line_items'][$line_item->getName()]['delete'] = array(
        '#ajax' => array(
          'callback' => [get_class($this), 'instantiate#ajaxDeleteSubmit#' . $plugin_id],
          'effect' => 'fade',
        ),
        '#limit_validation_errors' => [],
        '#submit' => array(array(get_class($this), 'deleteSubmit')),
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => Html::getId(implode('-', [$line_items_prefix, $line_item->getName(), 'delete']))
      );
    }

    // "Add more line items" button.
    $add_more_prefix = implode('-', array_merge($element['#parents'], ['add_more']));
    $element['add_more'] = array(
      '#access' => $element['#cardinality'] == self::CARDINALITY_UNLIMITED || count($line_items) < $element['#cardinality'],
      '#attributes' => array(
        'class' => array('payment-add-more'),
      ),
      '#id' => Html::getUniqueId($add_more_prefix),
      '#type' => 'container',
    );
    $options = [];
    foreach ($this->paymentLineItemManager->getDefinitions() as $line_item_plugin_id => $line_item_definition) {
      $options[$line_item_plugin_id] = $line_item_definition['label'];
    }
    natcasesort($options);
    $element['add_more']['type'] = array(
      '#options' => $options,
      '#title' => $this->t('Type'),
      '#type' => 'select',
    );
    $element['add_more']['add'] = array(
      '#ajax' => array(
        'callback' => [get_class($this), 'instantiate#ajaxAddMoreSubmit#' . $plugin_id],
        'effect' => 'fade',
      ),
      '#limit_validation_errors' => array(
        array_merge($element['#parents'], array('add_more', 'type')),
      ),
      '#submit' => array(function(array $form, FormStateInterface $form_state) use ($plugin_id) {
        /** @var \Drupal\Component\Plugin\PluginManagerInterface $element_info_manager */
        $element_info_manager = \Drupal::service('plugin.manager.element_info');
        /** @var \Drupal\payment\Element\PaymentLineItemsInput $element_plugin */
        $element_plugin = $element_info_manager->createInstance($plugin_id);
        $element_plugin->addMoreSubmit($form, $form_state);
      }),
      '#type' => 'submit',
      '#name' => str_replace('-', '_', $add_more_prefix . '_submit'),
      '#value' => $this->t('Add and configure a new line item'),
    );

    return $element;
  }

  /**
   * Implements form #element_validate callback.
   */
  public static function validate(array $element, FormStateInterface $form_state, array &$form) {
    // Reorder line items based on their weight elements.
    $line_items = [];
    $values = $form_state->getValues();
    $values = NestedArray::getValue($values, $element['#parents']);
    if ($values['line_items']) {
      foreach ($values['line_items'] as $name => $line_item_values) {
        $line_items[$name] = $line_item_values['weight'];
      }
      asort($line_items);
      foreach (static::getLineItems($element, $form_state) as $line_item) {
        $line_items[$line_item->getName()] = $line_item;
        $line_item->validateConfigurationForm($element['line_items'][$line_item->getName()]['plugin_form'], $form_state);
        // @todo Don't call the submit handler if plugin validation failed.
        $line_item->submitConfigurationForm($element['line_items'][$line_item->getName()]['plugin_form'], $form_state);
      }
      static::setLineItems($element, $form_state, array_values($line_items));
    }
  }

  /**
   * Implements form #submit callback.
   */
  public function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $root_element = NestedArray::getValue($form, $parents);
    $values = $form_state->getValues();
    $values = NestedArray::getValue($values, array_slice($triggering_element['#parents'], 0, -2));
    $line_item = $this->paymentLineItemManager->createInstance($values['add_more']['type']);
    $line_item->setName(static::createLineItemName($root_element, $form_state, $values['add_more']['type']));
    $line_items = static::getLineItems($root_element, $form_state);
    $line_items[] = $line_item;
    static::setLineItems($root_element, $form_state, $line_items);
    $form_state->setRebuild();
  }

  /**
   * Implements form AJAX callback.
   */
  public function ajaxAddMoreSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -2);
    $root_element = NestedArray::getValue($form, $parents);
    $response = new AjaxResponse();
    $selector = $this->getElementSelector($root_element['#id']);
    $response->addCommand(new ReplaceCommand("[data-drupal-selector=\"{$selector}\"]", $this->renderer->render($root_element)));

    return $response;
  }

  /**
   * Implements form #submit callback.
   */
  public static function deleteSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $root_element_parents  = array_slice($triggering_element['#array_parents'], 0, -3);
    $root_element = NestedArray::getValue($form, $root_element_parents);
    $parents = $triggering_element['#array_parents'];
    $line_item_name = $parents[count($parents) - 2];
    /** @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface[] $line_items */
    $line_items = array_values(static::getLineItems($root_element, $form_state));
    foreach ($line_items as $i => $line_item) {
      if ($line_item->getName() == $line_item_name) {
        unset($line_items[$i]);
      }
    }
    static::setLineItems($root_element, $form_state, $line_items);
    $form_state->setRebuild();
  }

  /**
   * Implements form AJAX callback.
   */
  public function ajaxDeleteSubmit(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $widget = NestedArray::getValue($form, array_splice($parents, 0, -3));
    $selector = $this->getElementSelector($widget['#id']);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("[data-drupal-selector=\"{$selector}\"]", $this->renderer->render($widget)));

    return $response;
  }

  /**
   * Creates a unique line item name.
   *
   * @param mixed[] $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param string $name
   *   The preferred name.
   *
   * @return string
   */
  protected static function createLineItemName(array $element, FormStateInterface $form_state, $name) {
    $counter = NULL;
    while (static::lineItemExists($element, $form_state, $name . $counter)) {
      $counter++;
    }

    return $name . $counter;
  }

  /**
   * Checks if a line item name already exists.
   *
   * @param mixed[] $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param string $name
   *
   * @return bool
   */
  protected static function lineItemExists(array $element, FormStateInterface $form_state, $name) {
    foreach (static::getLineItems($element, $form_state) as $line_item) {
      if ($line_item->getName() == $name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Stores the line items in the form's state.
   *
   * @param mixed[] $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface[] $line_items
   */
  protected static function setLineItems(array $element, FormStateInterface $form_state, array $line_items) {
    $form_state->set('payment.element.payment_line_items_input.configured.' . $element['#name'], $line_items);
  }

  /**
   * Retrieves the line items from the form's state.
   *
   * @param mixed[] $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface[]
   */
  public static function getLineItems(array $element, FormStateInterface $form_state) {
    $key = 'payment.element.payment_line_items_input.configured.' . $element['#name'];

    return $form_state->has($key) ? $form_state->get($key) : [];
  }

  /**
   * Gets the root element's HTML ID.
   *
   * @param mixed[] $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return string
   */
  protected static function getElementId(array $element, FormStateInterface $form_state) {
    $key = 'payment.element.payment_line_items_input.id.' . $element['#name'];

    if (!$form_state->has($key)) {
      $form_state->set($key, Html::getUniqueId('payment-element-payment_line_items_input'));
    }

    return $form_state->get($key);
  }


  /**
   * Gets de data-drupal-selector based on id
   *
   * @param string $id
   *   The id of the element
   *
   * @return mixed
   */
  private function getElementSelector($id)
  {
    if ($length = strpos($id, '--')) {
      $id = substr($id, 0, $length);
    }

    return Html::getId($id);
  }

  /**
   * Initializes stored line items.
   *
   * @param mixed[] $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return bool
   */
  protected static function initializeLineItems(array $element, FormStateInterface $form_state) {
    if (!$form_state->has('payment.element.payment_line_items_input.configured.' . $element['#name'])) {
      static::setLineItems($element, $form_state, $element['#default_value']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return static::getLineItems($element, $form_state);
  }



}
