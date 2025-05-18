<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}trm_categories ORDER BY name ASC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Tournament Categories</h1>
    <a href="#" class="page-title-action" id="add-category">Add New</a>
    
    <div id="category-form" style="display: none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
        <h2>Add New Category</h2>
        <form id="trm-category-form">
            <?php wp_nonce_field('trm_admin_nonce', 'trm_nonce'); ?>
            <input type="hidden" name="category_id" value="0">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="name">Name</label>
                    </th>
                    <td>
                        <input type="text" id="name" name="name" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="description">Description</label>
                    </th>
                    <td>
                        <textarea id="description" name="description" class="large-text" rows="5"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="status">Status</label>
                    </th>
                    <td>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Save Category</button>
                <button type="button" class="button" id="cancel-category">Cancel</button>
            </p>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($categories): ?>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($category->name); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="#" class="edit-category" data-id="<?php echo esc_attr($category->id); ?>">Edit</a> |
                                </span>
                                <span class="trash">
                                    <a href="#" class="delete-category" data-id="<?php echo esc_attr($category->id); ?>">Delete</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($category->description); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($category->status); ?>">
                                <?php echo esc_html(ucfirst($category->status)); ?>
                            </span>
                        </td>
                        <td>
                            <a href="#" class="button edit-category" data-id="<?php echo esc_attr($category->id); ?>">Edit</a>
                            <a href="#" class="button delete-category" data-id="<?php echo esc_attr($category->id); ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No categories found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/Hide Category Form
    $('#add-category').on('click', function(e) {
        e.preventDefault();
        $('#category-form').show();
        $('#trm-category-form')[0].reset();
        $('input[name="category_id"]').val('0');
    });

    $('#cancel-category').on('click', function() {
        $('#category-form').hide();
    });

    // Edit Category
    $('.edit-category').on('click', function(e) {
        e.preventDefault();
        const categoryId = $(this).data('id');
        
        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_get_category',
                nonce: trm_admin.nonce,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    const category = response.data;
                    $('#name').val(category.name);
                    $('#description').val(category.description);
                    $('#status').val(category.status);
                    $('input[name="category_id"]').val(category.id);
                    $('#category-form').show();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Save Category
    $('#trm-category-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'trm_save_category',
            nonce: trm_admin.nonce,
            category_id: $('input[name="category_id"]').val(),
            name: $('#name').val(),
            description: $('#description').val(),
            status: $('#status').val()
        };

        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Delete Category
    $('.delete-category').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this category?')) {
            return;
        }

        const categoryId = $(this).data('id');
        
        $.ajax({
            url: trm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'trm_delete_category',
                nonce: trm_admin.nonce,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });
});
</script> 