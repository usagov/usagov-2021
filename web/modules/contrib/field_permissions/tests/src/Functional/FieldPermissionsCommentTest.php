<?php

namespace Drupal\Tests\field_permissions\Functional;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;

/**
 * Test field permissions with the comment module and fields.
 *
 * @group field_permissions
 */
class FieldPermissionsCommentTest extends FieldPermissionsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['comment'];

  /**
   * Test comment subject.
   *
   * @var string
   */
  protected $commentSubject;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Add comment permissions to authenticated user.
    $permissions = [
      'post comments',
      'skip comment approval',
      'access comments',
      'edit own comments',
    ];
    foreach ($permissions as $permission) {
      $this->limitUserRole->grantPermission($permission);
    }
    $this->limitUserRole->save();

    // Allow the web user to access comments and administer comments. Private
    // fields should still be hidden.
    $this->webUserRole
      ->grantPermission('access comments')
      ->grantPermission('administer comments')
      ->save();

    $this->fieldName = 'comment_body';
    $this->commentSubject = 'Test subject comment';
    $this->fieldText = 'A comment';

    $this->entityDisplayRepository = $this->container->get('entity_display.repository');
  }

  /**
   * Test field permissions on comments.
   */
  public function testFieldPermissionComment() {
    // These are all run within a single test method to avoid unnecessary site
    // installs.
    $this->addCommentField();
    $this->checkBaseCommentFieldFunctionality();
    $this->checkPrivateCommentField();
    $this->checkPrivateFieldPermission();
    $this->checkCustomCommentField();
  }

  /**
   * Configure a comment field on the article node type.
   */
  protected function addCommentField() {
    $entity_manager = \Drupal::entityTypeManager();
    $bundle = 'article';
    $comment_type_storage = $entity_manager->getStorage('comment_type');

    $comment_type_id = 'comment';
    $entity_type = 'node';
    $field_name = 'comment';

    $comment_type_storage->create([
      'id' => $comment_type_id,
      'label' => 'Comment',
      'target_entity_type_id' => $entity_type,
      'description' => 'Default comment field',
    ])->save();

    $entity_manager->getStorage('field_storage_config')->create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'type' => 'comment',
      'settings' => [
        'comment_type' => $comment_type_id,
      ],
    ])->save();
    $entity_manager->getStorage('field_config')->create([
      'label' => 'Comments',
      'description' => '',
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'required' => 1,
      'default_value' => [
        [
          'status' => 2,
          'cid' => 0,
          'last_comment_name' => '',
          'last_comment_timestamp' => 0,
          'last_comment_uid' => 0,
        ],
      ],
    ])->save();
    // Entity form displays: assign widget settings for the 'default' form
    // mode, and hide the field in all other form modes.
    $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle, 'default')
      ->setComponent($field_name, [
        'type' => 'comment_default',
        'weight' => 20,
      ])
      ->save();
    // Entity view displays: assign widget settings for the 'default' view
    // mode, and hide the field in all other view modes.
    $this->entityDisplayRepository->getViewDisplay($entity_type, $bundle)
      ->setComponent($field_name, [
        'label' => 'above',
        'type' => 'comment_default',
        'weight' => 20,
      ])
      ->save();
    $field = $entity_manager->getStorage('field_config')->create([
      'label' => 'Comment',
      'bundle' => $comment_type_id,
      'required' => TRUE,
      'field_storage' => FieldStorageConfig::loadByName('comment', $this->fieldName),
    ]);
    $field->save();
    // Assign widget settings for the 'default' form mode.
    $this->entityDisplayRepository->getFormDisplay('comment', $comment_type_id, 'default')
      ->setComponent($this->fieldName, [
        'type' => 'text_textarea',
      ])
      ->save();
    // Assign display settings for the 'default' view mode.
    $this->entityDisplayRepository->getViewDisplay('comment', $comment_type_id)
      ->setComponent($this->fieldName, [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->save();
  }

  /**
   * Change the field permission to a given type via the UI.
   *
   * @param string $perm
   *   The permission type to set.
   * @param array $custom_permission
   *   The permissions to set.
   * @param string $path
   *   The path for the field edit form.
   */
  protected function setCommentFieldPermissions($perm, array $custom_permission, $path) {

    $this->drupalGet($path);
    if ($perm === FieldPermissionTypeInterface::ACCESS_PUBLIC || $perm === FieldPermissionTypeInterface::ACCESS_PRIVATE) {
      $edit = ['type' => $perm];
      $this->drupalPostForm(NULL, $edit, t('Save settings'));
    }
    elseif ($perm === FieldPermissionTypeInterface::ACCESS_CUSTOM && !empty($custom_permission)) {
      $custom_permission['type'] = $perm;
      $this->drupalPostForm(NULL, $custom_permission, t('Save settings'));
    }
  }

  /**
   * Ensures basic field functionality is working without field permissions.
   */
  protected function checkBaseCommentFieldFunctionality() {
    $edit = [];
    $this->drupalLogin($this->adminUser);
    // Node add.
    $this->drupalGet('node/add/article');
    $this->node = $this->drupalCreateNode(['type' => 'article', 'uid' => $this->limitedUser->id()]);
    $this->drupalGet('node/' . $this->node->id());
    // Add comment to node.
    $edit['subject[0][value]'] = $this->commentSubject;
    $edit[$this->fieldName . '[0][value]'] = $this->fieldText;
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->assertEscaped($this->fieldText);
    $this->assertSession()->assertEscaped($this->commentSubject);
    $this->drupalLogout();

    $this->drupalLogin($this->limitedUser);
    // Test visibility first comment by admin.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->assertEscaped($this->fieldText);
    $this->assertSession()->pageTextContains($this->commentSubject);
    // Add second comment to node.
    $edit = [];
    $edit['subject[0][value]'] = 'Limit User comment subject';
    $edit[$this->fieldName . '[0][value]'] = 'Limit User comment body';
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet('node/' . $this->node->id());
    // Test visibility second comment by limituser..
    $this->assertSession()->pageTextContains('Limit User comment subject');
    $this->assertSession()->pageTextContains('Limit User comment body');
    $this->drupalLogout();
  }

  /**
   * Tests the comment field when set to private.
   */
  protected function checkPrivateCommentField() {
    $path = 'admin/structure/comment/manage/comment/fields/comment.comment.' . $this->fieldName;
    $permission = [];
    $this->drupalLogin($this->adminUser);
    // Add perm to admin (admin field permissions).
    $this->adminUserRole->grantPermission('administer field permissions')->save();

    // Set Private field to comment body.
    $this->setCommentFieldPermissions(FieldPermissionTypeInterface::ACCESS_PRIVATE, $permission, $path);
    $this->drupalLogout();
    $this->drupalLogin($this->limitedUser);
    $this->drupalGet('node/' . $this->node->id());
    // Test hide body comment post by Adminuser but display subject..
    $this->assertSession()->pageTextContains($this->commentSubject);
    $this->assertSession()->pageTextNotContains($this->fieldText);
    // Test view your comment.
    $this->assertSession()->pageTextContains('Limit User comment subject');
    $this->assertSession()->pageTextContains('Limit User comment body');
    // Test edit your comment.
    $this->drupalGet('comment/2/edit');
    $this->assertSession()->pageTextContains('Limit User comment body');

    // Logout and access as anonymous.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('comment/2/edit');
    // Comment body should be hidden.
    $this->assertSession()->pageTextNotContains('Limit User comment body');
    $this->drupalLogout();
  }

  /**
   * Tests the comment field with custom permissions.
   */
  protected function checkCustomCommentField() {
    $path = 'admin/structure/comment/manage/comment/fields/comment.comment.' . $this->fieldName;
    $permission = [];
    $this->drupalLogin($this->adminUser);
    // Change custom permission view own field body.
    $perm = ['view own ' . $this->fieldName];
    $permission = $this->grantCustomPermissions($this->limitUserRole, $perm, $permission);
    $this->setCommentFieldPermissions(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission, $path);
    $this->drupalLogout();

    $this->drupalLogin($this->limitedUser);
    // View your comment but not view field body comment post by admin.
    $this->drupalGet('node/' . $this->node->id());
    // Hide body comment post by Adminuser.
    $this->assertSession()->pageTextNotContains($this->fieldText);
    $this->assertSession()->pageTextContains($this->commentSubject);
    $this->assertSession()->pageTextContains('Limit User comment subject');
    $this->assertSession()->pageTextContains('Limit User comment body');
    // Edit your comment not accesss to body field.
    $this->drupalGet('comment/2/edit');
    $this->assertSession()->pageTextNotContains('Limit User comment body');
    $this->drupalLogout();

    $this->drupalLogin($this->adminUser);
    // Custom permission add edit_own field body.
    $perm = ['edit own ' . $this->fieldName];
    $permission = $this->grantCustomPermissions($this->limitUserRole, $perm, $permission);
    $this->setCommentFieldPermissions(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission, $path);
    $this->drupalLogout();

    $this->drupalLogin($this->limitedUser);
    // Test edit your comment edit field body.
    $this->drupalGet('comment/2/edit');
    $this->assertSession()->pageTextContains('Limit User comment body');
    $this->drupalLogout();

    $this->drupalLogin($this->adminUser);
    // Add edit and view all comment.
    $perm = ['edit ' . $this->fieldName, 'view ' . $this->fieldName];
    $permission = $this->grantCustomPermissions($this->adminUserRole, $perm, $permission);
    $this->setCommentFieldPermissions(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission, $path);
    // view.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains('Limit User comment body');
    // edit.
    $this->drupalGet('comment/2/edit');
    $this->assertSession()->pageTextContains('Limit User comment body');
    $this->drupalLogout();
  }

  /**
   * Tests access to private field permission.
   */
  protected function checkPrivateFieldPermission() {
    $path = 'admin/structure/comment/manage/comment/fields/comment.comment.' . $this->fieldName;
    $permission = [];
    $this->drupalLogin($this->adminUser);
    $this->setCommentFieldPermissions(FieldPermissionTypeInterface::ACCESS_PRIVATE, $permission, $path);

    // Login a user that didn't author the node.
    $this->drupalLogin($this->webUser);

    // View.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextNotContains('Limit User comment body');
    // Edit.
    $this->drupalGet('comment/2/edit');
    $this->assertSession()->pageTextNotContains('Limit User comment body');
    // Add permission access user private field.
    $this->webUserRole->grantPermission('access private fields')->save();
    // View.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains('Limit User comment body');
    // Edit.
    $this->drupalGet('comment/2/edit');
    $this->assertSession()->pageTextContains('Limit User comment body');

    $this->drupalLogout();
  }

}
