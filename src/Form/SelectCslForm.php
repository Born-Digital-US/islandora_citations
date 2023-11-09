<?php

namespace Drupal\islandora_citations\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\islandora_citations\IslandoraCitationsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implementing a ajax form.
 */
class SelectCslForm extends FormBase {

  /**
   * Citation helper service.
   *
   * @var Drupal\islandora_citations\IslandoraCitationsHelper
   */
  protected $citationHelper;
  /**
   * CSL type value from block.
   *
   * @var string
   */
  private $blockCSLType;
  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(IslandoraCitationsHelper $citationHelper, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->citationHelper = $citationHelper;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('islandora_citations.helper'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'select_csl_ajax_submit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $block_storage = $this->entityTypeManager->getStorage('block');
    // Check if the value is set in block, newly added field.
    // Pass it in renderCitation.
    $blocks = $block_storage->loadMultiple();
    $cslItems = $this->citationHelper->getCitationEntityList();
    $default_csl = array_values($cslItems)[0];
    foreach ($blocks as $block) {
      $settings = $block->get('settings');
      if (isset($settings['id'])) {
        if ($settings['id'] == 'islandora_citations_display_citations') {
          $default_csl = !empty($settings['default_csl']) ? $settings['default_csl'] : array_values($cslItems)[0];
          $this->blockCSLType = $settings['default_csl_type'];
        }
      }
    }
    // Check default csl exist or not.
    if (!array_key_exists($default_csl, $cslItems)) {
      $default_csl = array_values($cslItems)[0];
    }
    $csl = !empty($default_csl) ? $this->getDefaultCitation($default_csl) : '';
    $form['csl_list'] = [
      '#type' => 'select',
      '#options' => $cslItems,
      '#empty_option' => $this->t('- Select csl -'),
      '#default_value' => $default_csl,
      '#ajax' => [
        'callback' => '::renderAjaxCitation',
        'wrapper' => 'formatted-citation',
        'method' => 'html',
        'event' => 'change',
      ],
      '#attributes' => ['aria-label' => $this->t('Select CSL')],
      '#theme_wrappers' => [],
    ];
    $form['formatted-citation'] = [
      '#type' => 'item',
      '#markup' => '<div id="formatted-citation">' . $csl . '</div>',
      '#theme_wrappers' => [],
    ];

    $form['#cache']['contexts'][] = 'url';
    $form['#theme'] = 'display_citations';
    return $form;
  }

  /**
   * Render CSL response on ajax call.
   */
  public function renderAjaxCitation(array $form, FormStateInterface $form_state) {
    $csl_name = $form_state->getValue('csl_list');
    if ($csl_name == '') {
      return [
        '#children' => '',
      ];
    }
    // Method call to render citation.
    $rendered = $this->renderCitation($csl_name);
    $response = [
      '#children' => $rendered['data'],
    ];

    return $form['data'] = $response;
  }

  /**
   * Submitting the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Fetching results for default csl.
   *
   * @param string $csl_name
   *   Block default csl name.
   */
  public function getDefaultCitation($csl_name) {
    if (empty($csl_name)) {
      return $this->t('Select CSL');
    }
    try {
      // Method call to render citation.
      $rendered = $this->renderCitation($csl_name);
      return $rendered['data'];
    }
    catch (\Throwable $e) {
      return $e->getMessage();
    }
  }

  /**
   * Get rendered data.
   *
   * @param string $csl_name
   *   Block default csl name.
   */
  private function renderCitation($csl_name) {
    $entity = $this->routeMatch->getParameter('node');
    $citationItems[] = $this->citationHelper->encodeEntityForCiteproc($entity);
    $blockCSLType = $this->blockCSLType;
    if (!isset($citationItems[0]->type)) {
      $citationItems[0]->type = $blockCSLType;
    }
    $style = $this->citationHelper->loadStyle($csl_name);
    $rendered = $this->citationHelper->renderWithCiteproc($citationItems, $style);
    return $rendered;
  }

}
