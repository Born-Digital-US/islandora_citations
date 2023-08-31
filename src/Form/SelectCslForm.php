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
    $default_style = [];
    $block_storage = $this->entityTypeManager->getStorage('block');
    $blocks = $block_storage->loadMultiple();
    $cslItems = $this->citationHelper->getCitationEntityList();
    $default_csl = array_values($cslItems)[0];
    foreach ($blocks as $block) {
      $settings = $block->get('settings');
      if (isset($settings['id'])) {
        if ($settings['id'] == 'islandora_citations_display_citations') {
          $default_csl = !empty($settings['default_csl']) ? $settings['default_csl'] : array_values($cslItems)[0];
          $default_style = $this->entityTypeManager->getStorage('islandora_citations')->getQuery()
            ->condition('id', $default_csl)
            ->execute();
        }
      }
    }
    if (empty($default_style)) {
      $i = 0;
      while ($i < count($cslItems)) {
        $csl = array_values($cslItems);
        $style = $this->entityTypeManager->getStorage('islandora_citations')->getQuery()
          ->condition('id', $csl[$i])
          ->execute();
        if (!empty($style)) {

          $default_csl = $csl[$i];
          break;
        }
        $i++;
      }
    }
    $form['csl_list'] = [
      '#type' => 'select',
      '#options' => $cslItems,
      '#empty_option' => $this->t('- Select csl -'),
      '#default_value' => $default_csl,
      '#ajax' => [
        'callback' => '::renderCitation',
        'wrapper' => 'formatted-citation',
        'method' => 'html',
        'event' => 'change',
      ],
      '#attributes' => ['aria-label' => $this->t('Select CSL')],
      '#theme_wrappers' => [],
    ];
    $form['formatted-citation'] = [
      '#type' => 'item',
      '#markup' => '<div id="formatted-citation">' . !empty($default_csl) ? $this->getDefaultCitation($default_csl) : '' . '</div>',
      '#theme_wrappers' => [],
    ];

    $form['#cache']['contexts'][] = 'url';
    $form['#theme'] = 'display_citations';
    return $form;
  }

  /**
   * Render CSL response on ajax call.
   */
  public function renderCitation(array $form, FormStateInterface $form_state) {
    $csl_name = $form_state->getValue('csl_list');
    if ($csl_name == '') {
      return [
        '#children' => '',
      ];
    }
    $entity = $this->routeMatch->getParameter('node');
    $citationItems[] = $this->citationHelper->encodeEntityForCiteproc($entity);

    $style = $this->citationHelper->loadStyle($csl_name);

    $rendered = $this->citationHelper->renderWithCiteproc($citationItems, $style);

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
    $entity = $this->routeMatch->getParameter('node');
    if (empty($csl_name)) {
      return $this->t('Select CSL');
    }
    try {
      $citationItems[] = $this->citationHelper->encodeEntityForCiteproc($entity);
      $style = $this->citationHelper->loadStyle($csl_name);
      $rendered = $this->citationHelper->renderWithCiteproc($citationItems, $style);
      return $rendered['data'];
    }
    catch (\Throwable $e) {
      return $e->getMessage();
    }
  }

}
