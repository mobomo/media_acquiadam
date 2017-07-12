<?php

namespace Drupal\Tests\media_webdam\unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\media_entity\Entity\Media;
use Drupal\media_webdam\Plugin\MediaEntity\Type\WebdamAsset;
use Drupal\Tests\UnitTestCase;

/**
 * Webdam asset plugin test.
 *
 * @group media_webdam
 */
class WebdamAssetPluginTest extends UnitTestCase {

  /**
   * Tests the providedFields method.
   */
  public function testProvidedFields() {
    $plugin = new WebdamAsset(
      [],
      'test_plugin',
      [],
      $this->getMock(EntityTypeManager::class),
      $this->getMock(EntityFieldManager::class),
      $this->getMock(Config::class)
    );

    $this->assertArrayHasKey('type', $plugin->providedFields());
  }

  /**
   * Tests the getField method.
   */
  public function testGetField() {
    $plugin = new WebdamAsset(
      [],
      'test_plugin',
      [],
      $this->getMock(EntityTypeManager::class),
      $this->getMock(EntityFieldManager::class),
      $this->getMock(Config::class)
    );

    $this->assertEquals('image', $plugin->getField(
      $this->getMock(Media::class), 'type')
    );
  }

}