<?php

namespace Drupal\media_webdam\Plugin\EntityBrowser\Widget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\WidgetBase;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\media_webdam\WebdamInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\media_entity\Entity\Media;
use Drupal\media_entity\Entity\MediaBundle;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Uses a view to provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "webdam",
 *   label = @Translation("Webdam"),
 *   description = @Translation("Webdam asset browser"),
 *   auto_select = FALSE
 * )
 */
class Webdam extends WidgetBase {

  /**
   * The webdam interface.
   *
   * @var \Drupal\media_webdam\WebdamInterface
   */
  protected $webdam;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Webdam constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\entity_browser\WidgetValidationManager $validation_manager
   * @param \Drupal\media_webdam\WebdamInterface $webdam_interface
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, WidgetValidationManager $validation_manager, WebdamInterface $webdam, EntityTypeBundleInfoInterface $entity_type_bundle_info){
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager);
    $this->webdam = $webdam;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('media_webdam.webdam'),
      $container->get('entity_type.bundle.info')
    );
  }

    /**
   * {@inheritdoc}
   *
   * TODO: This is a mega-function which needs to be refactored.  Therefore it has been thouroughly documented
   *
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    //Start by inheriting parent form
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    //How many values are allowed for this media field
    $field_cardinality = $form_state->get(['entity_browser', 'validators', 'cardinality', 'cardinality']);
    //This form is submitted and rebuilt when a folder is clicked.  The triggering element identifies which folder button was clicked
    $trigger_elem = $form_state->getTriggeringElement();
    //The webdam folder ID of the current folder being rendered - Start with zero which is the root folder
    $current_folder_id = 0;
    //The webdam folder object - Start with NULL to represent the root folder
    $current_folder = NULL;

    //If a button has been clicked that represents a webdam folder
    if (isset($trigger_elem['#name']) && $trigger_elem['#name'] == 'webdam_folder') {
      //Set the current folder id to the id of the folder that was clicked
      $current_folder_id = intval($trigger_elem['#webdam_folder_id']);
    }
    //If the current folder is not zero then fetch information about the sub folder being rendered
    if($current_folder_id !== 0){
      //Fetch the folder object from webdam
      $current_folder = $this->webdam->getFolder($current_folder_id);
      //Fetch a list of assets for the folder from webdam
      $folder_assets = $this->webdam->getFolderAssets($current_folder_id);
      //Store the list of folders for rendering later
      $folders = $folder_assets->folders;
      //Store the list of items/assets for rendering later
      $folder_items = $folder_assets->items;
    }else{
      //The webdam root folder is fetched differently because it can only contain subfolders (not assets)
      $folders = $this->webdam->getTopLevelFolders();
    }

    //Initial breadcrumb array representing the root folder only
    $breadcrumbs = [
      '0' => 'Home'
    ];
    //If the form has been rebuilt due to navigating between folders, look for the breadcrumb container
    if(isset($form_state->getCompleteForm()['widget'])){
      if(!empty($form_state->getCompleteForm()['widget']['breadcrumb-container']['#breadcrumbs'])){
        //If breadcrumbs already exist, use them instead of the initial default value
        $breadcrumbs = $form_state->getCompleteForm()['widget']['breadcrumb-container']['#breadcrumbs'];
      }
    }
    //If the folder being rendered is already in the breadcrumb trail and the breadcrumb trail is longer than 1 (i.e. root folder only)
    if(array_key_exists($current_folder_id,$breadcrumbs) && count($breadcrumbs) > 1){
      //This indicates that the user has navigated "Up" the folder structure 1 or more levels
      do{
        //Go to the end of the breadcrumb array
        end($breadcrumbs);
        //Fetch the folder id of the last breadcrumb
        $id = key($breadcrumbs);
        //If the current folder id does not match the folder id of the last breadcrumb
        if($id != $current_folder_id && count($breadcrumbs) > 1) {
          //Remove the last breadcrumb since the user has navigated "Up" at least 1 folder
          array_pop($breadcrumbs);
        }
        //If the folder id of the last breadcrumb does not equal the current folder id then keep removing breadcrumbs from the end
      }while($id != $current_folder_id && count($breadcrumbs) > 1);
    }
    //If the parent folder id of the current folder is in the breadcrumb trail then the user MIGHT have navigated down into a subfolder
    if(is_object($current_folder) && property_exists($current_folder, 'parent') && array_key_exists($current_folder->parent, $breadcrumbs)){
      //Go to the end of the breadcrumb array
      end($breadcrumbs);
      //If the last folder id in the breadcrumb equals the parent folder id of the current folder the the user HAS navigated down into a subfolder
      if(key($breadcrumbs) == $current_folder->parent){
        //Add the current folder to the breadcrumb
        $breadcrumbs[$current_folder_id] = $current_folder->name;
      }
    }
    //Reset the breadcrumb array so that it can be rendered in order
    reset($breadcrumbs);
    //Create a container for the breadcrumb
    $form['breadcrumb-container'] = [
      '#type' => 'container',
      //custom element property to store breadcrumbs array.  This is fetched from the form state every time the form is rebuilt due to navigating between folders
      '#breadcrumbs' => $breadcrumbs,
    ];
    //Add the breadcrumb buttons to the form
    foreach ($breadcrumbs as $folder_id => $folder_name){
      $form['breadcrumb-container'][$folder_id] = [
        '#type' => 'button',
        '#value' => $folder_name,
        '#name' => 'webdam_folder',
        '#webdam_folder_id' => $folder_id,
        '#webdam_parent_folder_id' => $folder_name,
        '#prefix' => '<span class="webdam-breadcrumb-trail">',
        '#suffix' => '</span>',
        '#attributes' => [
          'class' => ['webdam-browser-breadcrumb'],
        ]
      ];
    }

    //Add container for assets (and folder buttons)
    $form['asset-container'] = [
      '#type' => 'container',

    ];

    $parent = 0;
    if (is_object($current_folder) && property_exists($current_folder, 'parent')) {
      $parent = $current_folder->parent;
    }

    // Add folder buttons to form
    foreach ($folders as $folder){
      $form['asset-container'][$folder->id] = [
        '#type' => 'button',
        '#value' => $folder->name,
        '#name' => 'webdam_folder',
        '#webdam_folder_id' => $folder->id,
        '#webdam_parent_folder_id' => $parent,
        '#attributes' => [
          'class' => ['webdam-browser-asset'],
        ],
      ];
    }
    //Assets are rendered as #options for a checkboxes element.  Start with an empty array.
    $assets = [];

    //Add to the assets array
    if (isset($folder_items)) {
      foreach ($folder_items as $folder_item) {
        $assets[$folder_item->id] = $this->layoutMediaEntity($folder_item);
      }
    }

    // Add assets to form.
    $form['asset-container']['assets'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose one or more assets'),
      '#title_display' => 'invisible',
      '#options' => $assets,
      '#attached' => [
        'library' => [
          'media_webdam/webdam',
        ]
      ]
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $entities = [];
    $media_bundle = MediaBundle::load($this->configuration['bundle']);
    $asset_ids = $form_state->getValue(['assets'], []);
    $assets = $this->webdam->getAssetMultiple($asset_ids);
    foreach ($assets as $asset) {
      $entity_values = [
        'bundle' => $this->configuration['bundle'],
        'uid' => \Drupal::currentUser()->id(),
        'langcode' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
        'status' => ($asset->status == 'active' ? Media::PUBLISHED : Media::NOT_PUBLISHED),
        'name' => $asset->name,
        $media_bundle->type_configuration['source_field'] => $asset->id,
      ];
      foreach ($media_bundle->field_map as $entity_field => $mapped_field) {
        switch ($entity_field){
          case 'datecreated':
            $entity_values[$mapped_field] = $asset->date_created_unix;
            break;
          case 'datemodified':
            $entity_values[$mapped_field] = $asset->date_modified_unix;
            break;
          case 'datecaptured':
            $entity_values[$mapped_field] = $asset->datecapturedUnix;
            break;
          default:
            $entity_values[$mapped_field] = $asset->$entity_field;
        }
      }
      $entity = Media::create($entity_values);
      $entity->save();
      $entities[] = $entity;
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $assets = array_filter($form_state->getValue('assets'), function($var){ return $var !== 0;} );
      $field_cardinality = $form_state->get(['entity_browser', 'validators', 'cardinality', 'cardinality']);
      if($field_cardinality > 0 && count($assets) > $field_cardinality){
        $message = $this->formatPlural($field_cardinality, 'You can not select more than 1 entity.', 'You can not select more than @count entities.');
        $form_state->setError($form['widget']['asset-container']['assets'], $message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $assets = [];
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $assets = $this->prepareEntities($form,$form_state);
    }
    $this->selectEntities($assets, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'submit_text' => $this->t('Select assets'),
      ] +
      parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   *
   * TODO: Add more settings for configuring this widget
   *
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo('media');
    $media_bundles = MediaBundle::loadMultiple(array_keys($bundle_info));
    $bundles = array_map( function($item){
        return $item->label;
      },array_filter($media_bundles, function($item){
        return $item->type == 'webdam_asset';
      })
    );
    $form['bundle'] = [
      '#type' => 'container',
      'select' => [
        '#type' => 'select',
        '#title' => $this->t('Bundle'),
        '#options' => $bundles,
        '#default_value' => $bundle,
      ],
      '#attributes' => ['id' => 'bundle-wrapper-' . $this->uuid()],
    ];
    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['bundle'] = $this->configuration['bundle']['select'];
  }

  /**
   * Format display of one asset in media browser.
   *
   * @var \Drupal\media_webdam\Webdam $webdamAsset
   *
   * @return string
   */
  public function layoutMediaEntity($webdamAsset) {
    $assetName = $webdamAsset->name;

    if (!empty($webdamAsset->thumbnailurls)) {
      $thumbnail = '<img src="' . $webdamAsset->thumbnailurls[2]->url . '" alt="' . $assetName . '" />';
    } else {
      $thumbnail = '<span class="webdam-browser-empty">No preview available.</span>';
    }

    $element = '<div class="webdam-asset-checkbox">' . $thumbnail . '<p>' . $assetName . '</p></div>';

    return $element;
  }
}
