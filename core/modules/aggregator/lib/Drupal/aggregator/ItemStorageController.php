<?php

/**
 * @file
 * Contains \Drupal\aggregator\ItemStorageController.
 */

namespace Drupal\aggregator;

use Drupal\Core\Entity\DatabaseStorageControllerNG;
use Drupal\Core\Entity\EntityInterface;

/**
 * Controller class for aggregators items.
 *
 * This extends the Drupal\Core\Entity\DatabaseStorageController class, adding
 * required special handling for feed item entities.
 */
class ItemStorageController extends DatabaseStorageControllerNG {

  /**
   * Overrides Drupal\Core\Entity\DataBaseStorageController::create().
   */
  public function create(array $values) {
    $entity = parent::create($values);

    // Set an initial timestamp, this will be overwritten if known.
    $entity->timestamp->value = REQUEST_TIME;
    return $entity;
  }

  /**
   * Overrides Drupal\Core\Entity\DataBaseStorageController::attachLoad().
   */
  protected function attachLoad(&$queried_entities, $load_revision = FALSE) {
    parent::attachLoad($queried_entities, $load_revision);
    foreach ($queried_entities as $item) {
      $item->categories = db_query('SELECT c.title, c.cid FROM {aggregator_category_item} ci LEFT JOIN {aggregator_category} c ON ci.cid = c.cid WHERE ci.iid = :iid ORDER BY c.title', array(':iid' => $item->id()))->fetchAll();
    }
  }

  /**
   * Overrides Drupal\Core\Entity\DataBaseStorageController::preDelete().
   */
  protected function preDelete($entities) {
    parent::preDelete($entities);

    db_delete('aggregator_category_item')
      ->condition('iid', array_keys($entities), 'IN')
      ->execute();
  }

  /**
   * Overrides Drupal\Core\Entity\DataBaseStorageController::postSave().
   */
  protected function postSave(EntityInterface $entity, $update) {
    parent::postSave($entity, $update);

    $result = db_query('SELECT cid FROM {aggregator_category_feed} WHERE fid = :fid', array(':fid' => $entity->fid->value));
    foreach ($result as $category) {
      db_merge('aggregator_category_item')
        ->key(array(
          'iid' => $entity->id(),
          'cid' => $category->cid,
        ))
        ->execute();
    }
  }

  /**
   * Implements Drupal\Core\Entity\DataBaseStorageControllerNG::baseFieldDefinitions().
   */
  public function baseFieldDefinitions() {
    $fields['iid'] = array(
      'label' => t('ID'),
      'description' => t('The ID of the aggregor item.'),
      'type' => 'integer_field',
      'read-only' => TRUE,
    );
    $fields['fid'] = array(
      'label' => t('Aggregator feed ID'),
      'description' => t('The ID of the aggregator feed.'),
      'type' => 'integer_field',
    );
    $fields['title'] = array(
      'label' => t('Title'),
      'description' => t('The title of the feed item.'),
      'type' => 'string_field',
    );
    $fields['langcode'] = array(
      'label' => t('Language code'),
      'description' => t('The feed item language code.'),
      'type' => 'language_field',
    );
    $fields['link'] = array(
      'label' => t('Link'),
      'description' => t('The link of the feed item.'),
      'type' => 'uri_field',
    );
    $fields['author'] = array(
      'label' => t('Author'),
      'description' => t('The author of the feed item.'),
      'type' => 'string_field',
    );
    $fields['description'] = array(
      'label' => t('Description'),
      'description' => t('The body of the feed item.'),
      'type' => 'string_field',
    );
    $fields['timestamp'] = array(
      'label' => t('Posted timestamp'),
      'description' => t('Posted date of the feed item, as a Unix timestamp.'),
      'type' => 'integer_field',
    );
    $fields['guid'] = array(
      'label' => t('GUID'),
      'description' => t('Unique identifier for the feed item.'),
      'type' => 'string_field',
    );
    return $fields;
  }

}
