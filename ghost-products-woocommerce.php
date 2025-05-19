<?php
/*
Plugin Name: Ghost Products WooCommerce
Description: Crée des produits WooCommerce cachés (invisibles sur le front) avec AJAX et ajoute une vue admin dédiée.
Version: 1.1
Author: Agarta
*/

// 1. Ajouter le sous-menu "Ghost Products"
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        'Ghost Products',
        'Ghost Products',
        'manage_woocommerce',
        'ghost-products',
        'render_ghost_products_page'
    );
});

function render_ghost_products_page() {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Produits Fantômes</h1>
        <a href="#" class="page-title-action" onclick="openGhostProductModal(); return false;">Ajouter</a>
        <hr class="wp-header-end">

        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get">
                    <input type="hidden" name="post_type" value="product" />
                    <input type="hidden" name="ghost_only" value="1" />
                    <select name="ghost_filter">
                        <option value="">Tous les produits fantômes</option>
                        <option value="yes" <?php selected(isset($_GET['ghost_filter']) && $_GET['ghost_filter'] === 'yes'); ?>>Seulement fantômes</option>
                        <option value="no" <?php selected(isset($_GET['ghost_filter']) && $_GET['ghost_filter'] === 'no'); ?>>Sans fantômes</option>
                    </select>
                    <input type="submit" class="button" value="Filtrer">
                </form>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox">
                    </th>
                    <th scope="col" class="manage-column column-thumb">Image</th>
                    <th scope="col" class="manage-column column-title">Produit</th>
                    <th scope="col" class="manage-column column-price">Prix</th>
                    <th scope="col" class="manage-column column-categories">Catégories</th>
                    <th scope="col" class="manage-column column-brand">Marque</th>
                    <th scope="col" class="manage-column column-ghost-status">Statut</th>
                    <th scope="col" class="manage-column column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $args = [
                    'post_type' => 'product',
                    'posts_per_page' => 20,
                    'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
                    'meta_query' => [
                        [
                            'key' => '_catalog_visibility',
                            'value' => 'hidden'
                        ],
                    ],
                ];

                $query = new WP_Query($args);

                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $product = wc_get_product(get_the_ID());
                        $thumbnail = $product->get_image('thumbnail');
                        $price = $product->get_price_html();
                        $categories = wc_get_product_category_list(get_the_ID(), ', ');
                        $brands = get_the_terms(get_the_ID(), 'product_brand');
                        $brand = $brands ? $brands[0]->name : '';
                        $visibility = get_post_meta(get_the_ID(), '_catalog_visibility', true);
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="post[]" value="<?php echo get_the_ID(); ?>">
                            </th>
                            <td class="column-thumb">
                                <?php echo $thumbnail; ?>
                            </td>
                            <td class="column-title">
                                <strong>
                                    <a href="<?php echo get_edit_post_link(); ?>" class="row-title"><?php echo get_the_title(); ?></a>
                                </strong>
                            </td>
                            <td class="column-price">
                                <?php echo $price; ?>
                            </td>
                            <td class="column-categories">
                                <?php echo $categories; ?>
                            </td>
                            <td class="column-brand">
                                <?php echo $brand; ?>
                            </td>
                            <td class="column-ghost-status">
                                <span class="ghost-status <?php echo $visibility === 'hidden' ? 'hidden' : 'visible'; ?>">
                                    <?php echo $visibility === 'hidden' ? 'Fantôme' : 'Visible'; ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <a href="<?php echo get_edit_post_link(); ?>" class="button button-small">Modifier</a>
                                <button type="button" class="button button-small toggle-ghost" data-id="<?php echo get_the_ID(); ?>">
                                    <?php echo $visibility === 'hidden' ? 'Rendre visible' : 'Rendre fantôme'; ?>
                                </button>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="8">Aucun produit fantôme trouvé.</td>
                    </tr>
                    <?php
                }
                wp_reset_postdata();
                ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $query->max_num_pages,
                    'current' => get_query_var('paged') ? get_query_var('paged') : 1
                ]);
                ?>
            </div>
        </div>
    </div>

    <style>
        .ghost-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .ghost-status.hidden {
            background-color: #dc3232;
            color: white;
        }
        .ghost-status.visible {
            background-color: #46b450;
            color: white;
        }
        .column-thumb img {
            max-width: 50px;
            height: auto;
        }
        .column-actions {
            white-space: nowrap;
        }
        .column-actions .button {
            margin-right: 5px;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            $('.toggle-ghost').on('click', function() {
                const button = $(this);
                const productId = button.data('id');
                
                $.post(ajaxurl, {
                    action: 'toggle_ghost_visibility',
                    post_id: productId,
                    security: '<?php echo wp_create_nonce("toggle_ghost_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        const statusCell = button.closest('tr').find('.ghost-status');
                        if (response.data.new === 'hidden') {
                            statusCell.removeClass('visible').addClass('hidden').text('Fantôme');
                            button.text('Rendre visible');
                        } else {
                            statusCell.removeClass('hidden').addClass('visible').text('Visible');
                            button.text('Rendre fantôme');
                        }
                    } else {
                        alert('Erreur : ' + response.data);
                    }
                });
            });

            // Add formnovalidate to the main order update button
            $('#publish').attr('formnovalidate', 'formnovalidate');

            // Prevent validation of hidden modal fields on main form submit
            const $mainOrderForm = $('#publish').closest('form');

            if ($mainOrderForm.length) {
                $mainOrderForm.on('submit', function(e) {
                    // Get the required fields in the modal
                    const $nameField = $('#ghost_product_name');
                    const $priceField = $('#ghost_product_price');
                    
                    // Temporarily remove the required attribute
                    $nameField.data('original-required', $nameField.attr('required'));
                    $priceField.data('original-required', $priceField.attr('required'));
                    $nameField.removeAttr('required');
                    $priceField.removeAttr('required');

                    // Use a short timeout to allow form submission to proceed, then restore the attribute
                    setTimeout(function() {
                         if ($nameField.data('original-required') !== undefined) {
                             $nameField.attr('required', $nameField.data('original-required'));
                         }
                         if ($priceField.data('original-required') !== undefined) {
                              $priceField.attr('required', $priceField.data('original-required'));
                         }
                    }, 50);

                    // Note: We don't prevent default submission here
                });
            }
            
            // Fonction pour ouvrir la modale
            window.openGhostProductModal = function() {
                document.getElementById('ghost-product-modal').style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Re-add required attribute to fields when modal is shown
                document.getElementById('ghost_product_name').setAttribute('required', '');
                document.getElementById('ghost_product_price').setAttribute('required', '');

                // Remove the disabled attribute if it was set
                document.getElementById('ghost_product_name').disabled = false;
                document.getElementById('ghost_product_price').disabled = false;
            };

            // Fonction pour fermer la modale
            window.closeGhostProductModal = function() {
                document.getElementById('ghost-product-modal').style.display = 'none';
                document.body.style.overflow = 'auto';
                
                // Remove required attribute from fields when modal is hidden
                document.getElementById('ghost_product_name').removeAttribute('required');
                document.getElementById('ghost_product_price').removeAttribute('required');
                
                // Optionally disable the fields as well for good measure (they won't be submitted anyway)
                document.getElementById('ghost_product_name').disabled = true;
                document.getElementById('ghost_product_price').disabled = true;
            };

            // Fermer la modale en cliquant sur le X
            document.querySelector('.ghost-modal-close').onclick = closeGhostProductModal;

            // Fermer la modale en cliquant en dehors
            window.onclick = function(event) {
                const modal = document.getElementById('ghost-product-modal');
                if (event.target == modal) {
                    closeGhostProductModal();
                }
            };
            
            // Fonction pour ajouter un nouveau champ d'attribut
            window.addGhostAttribute = function() {
                const container = document.getElementById('ghost_attributes_container');
                const newRow = document.createElement('div');
                newRow.className = 'ghost-attribute-row';
                
                // Use standard string concatenation for PHP output
                let attributeOptions = '';
                <?php
                $attribute_taxonomies = wc_get_attribute_taxonomies();
                foreach ($attribute_taxonomies as $tax) {
                    echo 'attributeOptionsHtml += \'<option value="' . esc_attr($tax->attribute_name) . '">' . esc_html($tax->attribute_label) . '</option>\';';
                }
                ?>

                const newIndex = container.querySelectorAll('.ghost-attribute-row').length;

                newRow.innerHTML = '\n' +
                    '    <select class="ghost-attribute-select" name="ghost_attributes[' + newIndex + '][name]" style="width:45%;">\n' +
                    '        <option value="">Sélectionner un attribut</option>\n' +
                        attributeOptions +
                    '    </select>\n' +
                    '    <select class="ghost-attribute-value-select" name="ghost_attributes[' + newIndex + '][value]" style="width:45%;">\n' +
                    '        <option value="">Sélectionner une valeur</option>\n' +
                    '    </select>\n' +
                    '    <input type="text" class="ghost-attribute-value-new" placeholder="Nouvelle valeur" style="display:none; width:45%;" />\n' +
                    '    <button type="button" class="button" onclick="this.parentElement.remove()">-</button>\n' +
                    '';

                container.appendChild(newRow);
                
                // Initialiser Select2 pour le nouvel attribut
                const attributeSelect = $(newRow).find('.ghost-attribute-select');
                const valueSelect = $(newRow).find('.ghost-attribute-value-select');
                
                attributeSelect.select2({
                    placeholder: 'Sélectionner un attribut',
                    allowClear: true
                });
                
                valueSelect.select2({
                    placeholder: 'Sélectionner une valeur',
                    allowClear: true
                });
                
                // Charger les valeurs si un attribut est sélectionné
                attributeSelect.on('change', function() {
                    loadAttributeValues($(this), valueSelect, $(newRow).find('.ghost-attribute-value-new'));
                });
            };

            // Fonction pour gérer l'upload d'images
            window.ghostUploadImage = function(type) {
                const frame = wp.media({
                    title: type === 'main' ? 'Choisir l\'image principale' : 'Ajouter à la galerie',
                    multiple: type === 'gallery',
                    library: { type: 'image' }
                });

                frame.on('select', function() {
                    const attachments = frame.state().get('selection').map(item => item.toJSON());
                    
                    if (type === 'main') {
                        const imageId = attachments[0].id;
                        document.getElementById('ghost_product_image').value = imageId;
                        document.getElementById('ghost_image_preview').innerHTML =
                            '<img src="' + attachments[0].sizes.thumbnail.url + '" class="ghost-image-preview" />';
                    } else {
                        const galleryIds = attachments.map(img => img.id);
                        document.getElementById('ghost_product_gallery').value = galleryIds.join(',');
                        document.getElementById('ghost_gallery_preview').innerHTML = attachments
                            .map(img => '<img src="' + img.sizes.thumbnail.url + '" class="ghost-image-preview" />')
                            .join('');
                    }
                });

                frame.open();
            };

            // Fonction pour créer le produit
            window.createGhostProduct = function() {
                const name = document.getElementById('ghost_product_name').value;
                const price = document.getElementById('ghost_product_price').value;
                const taxClass = document.getElementById('ghost_product_tax_class').value;
                const categories = jQuery('#ghost_product_category').val();
                const brand = jQuery('#ghost_product_brand').val();
                const newBrand = document.getElementById('ghost_new_brand').value;
                const tag = document.getElementById('ghost_product_tag').value;
                const image = document.getElementById('ghost_product_image').value;
                const gallery = document.getElementById('ghost_product_gallery').value;

                // Récupération des attributs
                const attributes = [];
                document.querySelectorAll('.ghost-attribute-row').forEach(row => {
                    const attributeSelect = row.querySelector('.ghost-attribute-select');
                    const valueSelect = row.querySelector('.ghost-attribute-value-select');
                    const valueNew = row.querySelector('.ghost-attribute-value-new');
                    
                    if (attributeSelect.value) {
                        attributes.push({
                            name: attributeSelect.value,
                            value: valueSelect.value || valueNew.value
                        });
                    }
                });

                const data = {
                    action: 'create_ghost_product',
                    name,
                    price,
                    tax_class: taxClass,
                    categories,
                    brand,
                    newBrand,
                    tag,
                    image,
                    gallery,
                    attributes: JSON.stringify(attributes),
                    security: '<?php echo wp_create_nonce("create_ghost_product_nonce"); ?>',
                    order_id: jQuery('#custom_shipping_method').data('order-id') // Get order ID from shipping method select
                };

                document.getElementById('ajax-product-response').innerHTML = 'Création...';

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data)
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        document.getElementById('ajax-product-response').innerHTML =
                            '<div class="notice notice-success"><p>Produit créé : <strong>' + res.data.name + '</strong></p></div>';
                        // Réinitialiser tous les champs
                        document.getElementById('ghost_product_name').value = '';
                        document.getElementById('ghost_product_price').value = '';
                        document.getElementById('ghost_product_tax_class').value = '';
                        jQuery('#ghost_product_category').val(null).trigger('change');
                        jQuery('#ghost_product_brand').val(null).trigger('change');
                        document.getElementById('ghost_new_brand').value = '';
                        document.getElementById('ghost_product_tag').value = '';
                        document.getElementById('ghost_product_image').value = '';
                        document.getElementById('ghost_product_gallery').value = '';
                        document.getElementById('ghost_image_preview').innerHTML = '';
                        document.getElementById('ghost_gallery_preview').innerHTML = '';
                        document.getElementById('ghost_attributes_container').innerHTML = ''; // Clear existing attribute rows
                        window.addGhostAttribute(); // Add a default empty row

                        // Réinitialiser Select2 pour les attributs
                        jQuery('.ghost-attribute-select').select2({
                            placeholder: 'Sélectionner un attribut',
                            allowClear: true
                        });
                        jQuery('.ghost-attribute-value-select').select2({
                            placeholder: 'Sélectionner une valeur',
                            allowClear: true
                        });
                        // Fermer la modale après 2 secondes
                        setTimeout(closeGhostProductModal, 2000);

                        // Trigger an event to refresh order totals display
                        // Replacing: jQuery('.woocommerce_order_items').trigger('change');
                        // Simulate click on the standard calculate button to refresh totals display
                        const btn = jQuery('.calculate-action');
                        if (btn.length) {
                             // Re-enable button if it's disabled by WooCommerce
                             if (btn.is(':disabled')) {
                                  btn.prop('disabled', false);
                                 }
                                 btn.trigger('click');
                            }


                    } else {
                        document.getElementById('ajax-product-response').innerHTML =
                            '<div class="notice notice-error"><p>' + res.data + '</p></div>';
                    }
                });
            };

            // Attach click event to the button to open the modal
            $('#create-ghost-product-button').on('click', function() {
                openGhostProductModal();
            });

        }); // End of jQuery(document).ready()

    </script>
    <?php
}

// 2. Ajouter la metabox sur les commandes WooCommerce
add_action('add_meta_boxes', function () {
    global $post;
    if ($post && $post->post_type === 'shop_order') {
        add_meta_box(
            'quick_create_product',
            'Créer et ajouter un produit fantôme à la commande',
            'render_ghost_product_box',
            'shop_order',
            'side',
            'high'
        );
    }
});

// 3. HTML + JS AJAX dans la metabox
function render_ghost_product_box() {
    ?>
    <button type="button" class="button button-primary" id="create-ghost-product-button" style="white-space: normal;">Créer un produit fantôme et l'ajouter à la commande</button>

    <?php
}

// Add modal HTML, styles, and scripts to admin footer
add_action('admin_footer', function() {
    global $post;
    // Only load the modal on order edit pages
    if ($post && $post->post_type === 'shop_order') {
        ?>
        <!-- Modal -->
        <div id="ghost-product-modal" style="display:none;">
            <div class="ghost-modal-content">
                <div class="ghost-modal-header">
                    <h2>Créer un produit fantôme</h2>
                    <span class="ghost-modal-close">&times;</span>
                </div>
                <div class="ghost-modal-body">
                    <div id="ajax-product-response"></div>

                    <!-- Informations de base -->
                    <div class="ghost-product-field">
                        <label for="ghost_product_name">Nom du produit *</label>
                        <input type="text" id="ghost_product_name" name="ghost_product_name" placeholder="Nom du produit" style="width:100%; margin-bottom:5px;" required />
                    </div>

                    <div class="ghost-product-field">
                        <label for="ghost_product_price">Prix *</label>
                        <input type="number" id="ghost_product_price" name="ghost_product_price" placeholder="Prix" step="0.01" style="width:100%; margin-bottom:5px;" required />
                    </div>

                    <!-- Taxes -->
                    <div class="ghost-product-field">
                        <label for="ghost_product_tax_class">Classe de taxe</label>
                        <select id="ghost_product_tax_class" name="ghost_product_tax_class" style="width:100%; margin-bottom:5px;">
                            <option value="">Standard</option>
                            <?php
                            $tax_classes = WC_Tax::get_tax_classes();
                            foreach ($tax_classes as $tax_class) {
                                $tax_class_name = sanitize_title($tax_class);
                                echo '<option value="' . esc_attr($tax_class_name) . '">' . esc_html($tax_class) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Catégorie -->
                    <div class="ghost-product-field">
                        <label for="ghost_product_category">Catégorie</label>
                        <div class="ghost-category-selector">
                            <select id="ghost_product_category" name="ghost_product_category[]" class="wc-enhanced-select" multiple="multiple" style="width:100%; margin-bottom:5px;">
                                <?php
                                $product_categories = get_terms('product_cat', array('hide_empty' => false));
                                foreach ($product_categories as $category) {
                                    echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Marque (Brand) -->
                    <div class="ghost-product-field">
                        <label for="ghost_product_brand">Marque</label>
                        <div class="ghost-brand-selector">
                            <select id="ghost_product_brand" name="ghost_product_brand" class="wc-enhanced-select" style="width:100%; margin-bottom:5px;">
                                <option value="">Sélectionner une marque</option>
                                <?php
                                $brands = get_terms('product_brand', array('hide_empty' => false));
                                foreach ($brands as $brand) {
                                    echo '<option value="' . esc_attr($brand->term_id) . '">' . esc_html($brand->name) . '</option>';
                                }
                                ?>
                            </select>
                            <input type="text" id="ghost_new_brand" name="ghost_new_brand" placeholder="Ou créer une nouvelle marque" style="width:100%; margin-top:5px;" />
                        </div>
                    </div>

                    <!-- Étiquette -->
                    <div class="ghost-product-field">
                        <label for="ghost_product_tag">Étiquette</label>
                        <input type="text" id="ghost_product_tag" name="ghost_product_tag" placeholder="Étiquette" style="width:100%; margin-bottom:5px;" />
                    </div>

                    <!-- Image principale -->
                    <div class="ghost-product-field">
                        <label>Image principale</label>
                        <div class="ghost-image-upload">
                            <input type="hidden" id="ghost_product_image" name="ghost_product_image" value="" />
                            <div id="ghost_image_preview" style="margin-bottom:5px;"></div>
                            <button type="button" class="button" onclick="ghostUploadImage('main')">Choisir une image</button>
                        </div>
                    </div>

                    <!-- Galerie d'images -->
                    <div class="ghost-product-field">
                        <label>Galerie d'images</label>
                        <div class="ghost-gallery-upload">
                            <input type="hidden" id="ghost_product_gallery" name="ghost_product_gallery" value="" />
                            <div id="ghost_gallery_preview" style="margin-bottom:5px;"></div>
                            <button type="button" class="button" onclick="ghostUploadImage('gallery')">Ajouter à la galerie</button>
                        </div>
                    </div>

                    <!-- Attributs -->
                    <div class="ghost-product-field">
                        <label>Attributs</label>
                        <div id="ghost_attributes_container">
                            <div class="ghost-attribute-row">
                                <select class="ghost-attribute-select" name="ghost_attributes[0][name]" style="width:45%;">
                                    <option value="">Sélectionner un attribut</option>
                                    <?php
                                    $attribute_taxonomies = wc_get_attribute_taxonomies();
                                    foreach ($attribute_taxonomies as $tax) {
                                        echo '<option value="' . esc_attr($tax->attribute_name) . '">' . esc_html($tax->attribute_label) . '</option>';
                                    }
                                    ?>
                                </select>
                                <select class="ghost-attribute-value-select" name="ghost_attributes[0][value]" style="width:45%; शीघ्र loading..."></select>
                                <input type="text" class="ghost-attribute-value-new" placeholder="Nouvelle valeur" style="display:none; width:45%;" />
                                <button type="button" class="button" onclick="addGhostAttribute()">+</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ghost-modal-footer">
                    <button type="button" class="button button-primary" onclick="createGhostProduct()">Créer le produit</button>
                    <button type="button" class="button" onclick="closeGhostProductModal()">Annuler</button>
                </div>
            </div>
        </div>

        <style>
            #ghost-product-modal {
                display: none;
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .ghost-modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 800px;
                max-height: 90vh;
                overflow-y: auto;
                position: relative;
            }
            .ghost-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }
            .ghost-modal-close {
                color: #aaa;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .ghost-modal-close:hover {
                color: black;
            }
            .ghost-modal-body {
                margin-bottom: 20px;
            }
            .ghost-modal-footer {
                text-align: right;
                padding-top: 10px;
                border-top: 1px solid #ddd;
            }
            .ghost-product-field { margin-bottom: 15px; }
            .ghost-product-field label { display: block; margin-bottom: 5px; font-weight: bold; }
            .ghost-image-preview { max-width: 100px; margin: 5px; }
            .ghost-attribute-row { margin-bottom: 5px; display: flex; gap: 5px; }
            .select2-container { width: 100% !important; }
        </style>

        <script>
            jQuery(document).ready(function($) {

                // --- Modal Functions ---

                // Fonction pour ouvrir la modale
                window.openGhostProductModal = function() {
                    document.getElementById('ghost-product-modal').style.display = 'block';
                    document.body.style.overflow = 'hidden';

                    // Re-add required attribute to fields when modal is shown
                    document.getElementById('ghost_product_name').setAttribute('required', '');
                    document.getElementById('ghost_product_price').setAttribute('required', '');

                    // Remove the disabled attribute if it was set
                    document.getElementById('ghost_product_name').disabled = false;
                    document.getElementById('ghost_product_price').disabled = false;
                };

                // Fonction pour fermer la modale
                window.closeGhostProductModal = function() {
                    document.getElementById('ghost-product-modal').style.display = 'none';
                    document.body.style.overflow = 'auto';

                    // Remove required attribute from fields when modal is hidden
                    document.getElementById('ghost_product_name').removeAttribute('required');
                    document.getElementById('ghost_product_price').removeAttribute('required');

                    // Optionally disable the fields as well for good measure (they won't be submitted anyway)
                    document.getElementById('ghost_product_name').disabled = true;
                    document.getElementById('ghost_product_price').disabled = true;

                     // Clear any previous AJAX response messages
                     $('#ajax-product-response').empty();
                };

                // Fermer la modale en cliquant sur le X
                document.querySelector('.ghost-modal-close').onclick = closeGhostProductModal;

                // Fermer la modale en cliquant en dehors
                window.onclick = function(event) {
                    const modal = document.getElementById('ghost-product-modal');
                    if (event.target == modal) {
                        closeGhostProductModal();
                    }
                };

                // --- Form Initialization and Handlers ---

                // Initialiser Select2 pour les catégories
                $('#ghost_product_category').select2({
                    placeholder: 'Sélectionner des catégories',
                    allowClear: true
                });

                // Initialiser Select2 pour les marques
                $('#ghost_product_brand').select2({
                    placeholder: 'Sélectionner une marque',
                    allowClear: true
                });

                // Initialiser Select2 pour la classe de taxe
                $('#ghost_product_tax_class').select2({
                    // Remove the placeholder option
                    // placeholder: 'Sélectionner une classe de taxe',
                    allowClear: true
                });

                // Remove the explicit value setting as it might not be needed without the placeholder
                // $('#ghost_product_tax_class').val('').trigger('change.select2');

                // Gérer la création de nouvelle marque
                $('#ghost_new_brand').on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        const newBrand = $(this).val();
                        if (newBrand) {
                            $.post(ajaxurl, {
                                action: 'create_product_brand',
                                brand_name: newBrand,
                                security: '<?php echo wp_create_nonce("create_brand_nonce"); ?>'
                            }, function(response) {
                                if (response.success) {
                                    const option = new Option(newBrand, response.data.term_id, true, true);
                                    $('#ghost_product_brand').append(option).trigger('change');
                                    $('#ghost_new_brand').val('');
                                } else {
                                     alert('Erreur lors de la création de la marque : ' + response.data);
                                }
                            });
                        }
                    }
                });

                // Fonction pour charger les valeurs d'un attribut
                function loadAttributeValues(attributeSelect, valueSelect, valueNew) {
                    const attributeName = attributeSelect.val();

                    if (attributeName) {
                        // Afficher un indicateur de chargement
                        valueSelect.html('<option value="">Chargement...</option>');
                        valueSelect.show();
                        valueNew.hide();

                        $.post(ajaxurl, {
                            action: 'get_attribute_values',
                            attribute_name: attributeName,
                            security: '<?php echo wp_create_nonce("get_attribute_values_nonce"); ?>'
                        }, function(response) {
                            if (response.success) {
                                valueSelect.html(response.data.options);
                                // Réinitialiser Select2
                                valueSelect.select2('destroy').select2({
                                    placeholder: 'Sélectionner une valeur',
                                    allowClear: true
                                });
                            } else {
                                // Handle error, perhaps show an empty select or an error message
                                valueSelect.html('<option value="">Erreur de chargement</option>');
                                valueSelect.select2('destroy').select2({
                                    placeholder: 'Sélectionner une valeur',
                                    allowClear: true
                                });
                                alert('Erreur lors du chargement des valeurs d\'attribut : ' + response.data);
                            }
                        });
                    } else {
                        // If no attribute is selected, clear and hide value select, hide new value input
                         if (valueSelect.data('select2')) {
                            valueSelect.select2('destroy');
                        }
                        valueSelect.html('<option value="">Sélectionner une valeur</option>').hide();
                        valueNew.val('').hide();
                    }
                }

                // Gérer les attributs
                $(document).on('change', '.ghost-attribute-select', function() {
                    const valueSelect = $(this).siblings('.ghost-attribute-value-select');
                    const valueNew = $(this).siblings('.ghost-attribute-value-new');

                    // Détruire l'instance Select2 existente si elle existe
                    if (valueSelect.data('select2')) {
                        valueSelect.select2('destroy');
                    }

                    loadAttributeValues($(this), valueSelect, valueNew);
                });

                 // Gérer le focus sur le champ "Nouvelle valeur" pour cacher le sélecteur
                $(document).on('focus', '.ghost-attribute-value-new', function() {
                     $(this).siblings('.ghost-attribute-value-select').hide();
                });

                 // Gérer le changement de valeur dans le sélecteur pour cacher le champ "Nouvelle valeur"
                 $(document).on('change', '.ghost-attribute-value-select', function() {
                      if ($(this).val()) {
                           $(this).siblings('.ghost-attribute-value-new').val('').hide();
                      } else {
                           // If "Sélectionner une valeur" is chosen, show the "Nouvelle valeur" field
                           $(this).siblings('.ghost-attribute-value-new').show();
                      }
                 });


                // Gérer la création de nouvelle valeur d'attribut
                $(document).on('keypress', '.ghost-attribute-value-new', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        const attributeSelect = $(this).siblings('.ghost-attribute-select');
                        const valueNewInput = $(this);
                        const attributeName = attributeSelect.val();
                        const newValue = valueNewInput.val();

                        if (attributeName && newValue) {
                            $.post(ajaxurl, {
                                action: 'create_attribute_value',
                                attribute_name: attributeName,
                                value: newValue,
                                security: '<?php echo wp_create_nonce("create_attribute_value_nonce"); ?>'
                            }, function(response) {
                                if (response.success) {
                                    const valueSelect = valueNewInput.siblings('.ghost-attribute-value-select');
                                    const option = new Option(newValue, response.data.term_id, true, true);

                                    // Remove existing options and add the new one, then re-initialize Select2
                                    valueSelect.empty().append(option).trigger('change');
                                    valueSelect.select2('destroy').select2({
                                         placeholder: 'Sélectionner une valeur',
                                         allowClear: true
                                    });

                                    valueNewInput.val('').hide();
                                    valueSelect.show();
                                } else {
                                    alert('Erreur lors de la création de la valeur d\'attribut : ' + response.data);
                                }
                            });
                        }
                    }
                });

                // Fonction pour ajouter un nouveau champ d'attribut
                window.addGhostAttribute = function() {
                    const container = document.getElementById('ghost_attributes_container');
                    const newRow = document.createElement('div');
                    newRow.className = 'ghost-attribute-row';

                    // Build attribute options HTML using PHP
                    let attributeOptionsHtml = '';
                    <?php
                    $attribute_taxonomies = wc_get_attribute_taxonomies();
                    foreach ($attribute_taxonomies as $tax) {
                        // Correctly escape and format the PHP output for JavaScript string
                        echo 'attributeOptionsHtml += \'<option value="' . esc_attr($tax->attribute_name) . '">' . esc_html($tax->attribute_label) . '</option>\';';
                    }
                    ?>

                    const newIndex = container.querySelectorAll('.ghost-attribute-row').length;

                    newRow.innerHTML =
                        '<select class="ghost-attribute-select" name="ghost_attributes[' + newIndex + '][name]" style="width:45%;">' +
                            '<option value="">Sélectionner un attribut</option>' +
                            attributeOptionsHtml +
                        '</select>' +
                        '<select class="ghost-attribute-value-select" name="ghost_attributes[' + newIndex + '][value]" style="width:45%;">' +
                            '<option value="">Sélectionner une valeur</option>' +
                        '</select>' +
                        '<input type="text" class="ghost-attribute-value-new" placeholder="Nouvelle valeur" style="display:none; width:45%;" />' +
                        '<button type="button" class="button" onclick="this.parentElement.remove()">-</button>';

                    container.appendChild(newRow);

                    // Initialiser Select2 pour le nouvel attribut
                    const attributeSelect = $(newRow).find('.ghost-attribute-select');
                    const valueSelect = $(newRow).find('.ghost-attribute-value-select');

                    attributeSelect.select2({
                        placeholder: 'Sélectionner un attribut',
                        allowClear: true
                    });

                    valueSelect.select2({
                        placeholder: 'Sélectionner une valeur',
                        allowClear: true
                    });

                    // Charger les valeurs si un attribut est sélectionné
                    attributeSelect.on('change', function() {
                        loadAttributeValues($(this), valueSelect, $(newRow).find('.ghost-attribute-value-new'));
                    });
                };

                // Fonction pour gérer l'upload d'images
                window.ghostUploadImage = function(type) {
                    const frame = wp.media({
                        title: type === 'main' ? 'Choisir l\'image principale' : 'Ajouter à la galerie',
                        multiple: type === 'gallery',
                        library: { type: 'image' }
                    });

                    frame.on('select', function() {
                        const attachments = frame.state().get('selection').map(item => item.toJSON());

                        if (type === 'main') {
                            const imageId = attachments[0].id;
                            document.getElementById('ghost_product_image').value = imageId;
                            document.getElementById('ghost_image_preview').innerHTML =
                                '<img src="' + attachments[0].sizes.thumbnail.url + '" class="ghost-image-preview" />';
                        } else {
                            const galleryIds = attachments.map(img => img.id);
                            document.getElementById('ghost_product_gallery').value = galleryIds.join(',');
                            document.getElementById('ghost_gallery_preview').innerHTML = attachments
                                .map(img => '<img src="' + img.sizes.thumbnail.url + '" class="ghost-image-preview" />')
                                .join('');
                        }
                    });

                    frame.open();
                };

                // Fonction pour créer le produit
                window.createGhostProduct = function() {
                    const name = document.getElementById('ghost_product_name').value;
                    const price = document.getElementById('ghost_product_price').value;
                    const taxClass = document.getElementById('ghost_product_tax_class').value;
                    const categories = jQuery('#ghost_product_category').val();
                    const brand = jQuery('#ghost_product_brand').val();
                    const newBrand = document.getElementById('ghost_new_brand').value;
                    const tag = document.getElementById('ghost_product_tag').value;
                    const image = document.getElementById('ghost_product_image').value;
                    const gallery = document.getElementById('ghost_product_gallery').value;

                    // Récupération des attributs
                    const attributes = [];
                    document.querySelectorAll('.ghost-attribute-row').forEach(row => {
                        const attributeSelect = row.querySelector('.ghost-attribute-select');
                        const valueSelect = row.querySelector('.ghost-attribute-value-select');
                        const valueNew = row.querySelector('.ghost-attribute-value-new');

                        if (attributeSelect.value) {
                            attributes.push({
                                name: attributeSelect.value,
                                value: valueSelect.value || valueNew.value
                            });
                        }
                    });

                    const data = {
                        action: 'create_ghost_product',
                        name,
                        price,
                        tax_class: taxClass,
                        categories,
                        brand,
                        newBrand,
                        tag,
                        image,
                        gallery,
                        attributes: JSON.stringify(attributes),
                        security: '<?php echo wp_create_nonce("create_ghost_product_nonce"); ?>',
                        order_id: jQuery('#custom_shipping_method').data('order-id') // Get order ID from shipping method select
                    };

                    document.getElementById('ajax-product-response').innerHTML = 'Création...';

                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(data)
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            document.getElementById('ajax-product-response').innerHTML =
                                '<div class="notice notice-success"><p>Produit créé : <strong>' + res.data.name + '</strong></p></div>';
                            // Réinitialiser tous les champs
                            document.getElementById('ghost_product_name').value = '';
                            document.getElementById('ghost_product_price').value = '';
                            document.getElementById('ghost_product_tax_class').value = '';
                            jQuery('#ghost_product_category').val(null).trigger('change');
                            jQuery('#ghost_product_brand').val(null).trigger('change');
                            document.getElementById('ghost_new_brand').value = '';
                            document.getElementById('ghost_product_tag').value = '';
                            document.getElementById('ghost_product_image').value = '';
                            document.getElementById('ghost_product_gallery').value = '';
                            document.getElementById('ghost_image_preview').innerHTML = '';
                            document.getElementById('ghost_gallery_preview').innerHTML = '';
                            document.getElementById('ghost_attributes_container').innerHTML = ''; // Clear existing attribute rows
                            window.addGhostAttribute(); // Add a default empty row

                            // Réinitialiser Select2 pour les attributs
                            jQuery('.ghost-attribute-select').select2({
                                placeholder: 'Sélectionner un attribut',
                                allowClear: true
                            });
                            jQuery('.ghost-attribute-value-select').select2({
                                placeholder: 'Sélectionner une valeur',
                                allowClear: true
                            });
                            // Fermer la modale après 2 secondes
                            setTimeout(closeGhostProductModal, 2000);

                            // Trigger an event to refresh order totals display
                            // Replacing: jQuery('.woocommerce_order_items').trigger('change');
                            // Simulate click on the standard calculate button to refresh totals display
                            const btn = jQuery('.calculate-action');
                            if (btn.length) {
                                 // Re-enable button if it's disabled by WooCommerce
                                 if (btn.is(':disabled')) {
                                      btn.prop('disabled', false);
                                     }
                                     btn.trigger('click');
                                }


                        } else {
                            document.getElementById('ajax-product-response').innerHTML =
                                '<div class="notice notice-error"><p>' + res.data + '</p></div>';
                        }
                    });
                };

                // Attach click event to the button to open the modal
                $('#create-ghost-product-button').on('click', function() {
                    openGhostProductModal();
                });

                // Prevent validation of hidden modal fields on main form submit
                const $mainOrderForm = $('#publish').closest('form');

                if ($mainOrderForm.length) {
                    $mainOrderForm.on('submit', function(e) {
                        // Get the required fields in the modal
                        const $nameField = $('#ghost_product_name');
                        const $priceField = $('#ghost_product_price');

                        // Temporarily remove the required attribute
                        $nameField.data('original-required', $nameField.attr('required'));
                        $priceField.data('original-required', $priceField.attr('required'));
                        $nameField.removeAttr('required');
                        $priceField.removeAttr('required');

                        // Use a short timeout to allow form submission to proceed, then restore the attribute
                        setTimeout(function() {
                             if ($nameField.data('original-required') !== undefined) {
                                 $nameField.attr('required', $nameField.data('original-required'));
                             }
                             if ($priceField.data('original-required') !== undefined) {
                                  $priceField.attr('required', $priceField.data('original-required'));
                             }
                        }, 50);

                        // Note: We don't prevent default submission here
                    });
                }

            }); // End of jQuery(document).ready()

        </script>
        <?php
    }
});

// Fonction de logging pour le débogage
function ghost_log_error($message, $data = null) {
    $log_file = WP_CONTENT_DIR . '/ghost-products-debug.log';
    $timestamp = current_time('mysql');
    $log_message = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $log_message .= "\nData: " . print_r($data, true);
    }
    
    $log_message .= "\n" . str_repeat('-', 80) . "\n";
    
    error_log($log_message, 3, $log_file);
}

// Mise à jour de la fonction de création de produit pour inclure le logging
add_action('wp_ajax_create_ghost_product', function () {
    try {
        error_log('create_ghost_product AJAX called. POST data: ' . print_r($_POST, true));
        check_ajax_referer('create_ghost_product_nonce', 'security');
        
        // Nettoyage des données
        $name = sanitize_text_field($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $tax_class = sanitize_text_field($_POST['tax_class'] ?? '');
        $categories = array_map('intval', explode(',', $_POST['categories'] ?? ''));
        $brand = intval($_POST['brand'] ?? 0);
        $newBrand = sanitize_text_field($_POST['newBrand'] ?? '');
        $tag = sanitize_text_field($_POST['tag'] ?? '');
        $image = intval($_POST['image'] ?? 0);
        $gallery = array_map('intval', explode(',', $_POST['gallery'] ?? ''));
        $raw_attributes = json_decode(stripslashes($_POST['attributes'] ?? '[]'), true);

        // Validation des données requises
        if (!$name) {
            wp_send_json_error("Nom requis.");
            return;
        }
        if (!$price) {
            wp_send_json_error("Prix requis.");
            return;
        }

        // Création du produit
        $post_data = array(
            'post_title' => $name,
            'post_type' => 'product',
            'post_status' => 'publish'
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error("Erreur lors de la création du produit: " . $post_id->get_error_message());
            return;
        }

        // Mise à jour des métadonnées du produit
        update_post_meta($post_id, '_regular_price', $price);
        update_post_meta($post_id, '_price', $price);
        update_post_meta($post_id, '_catalog_visibility', 'hidden');
        
        // Gestion des taxes
        if ($tax_class) {
            update_post_meta($post_id, '_tax_class', $tax_class);
        }

        // Définir les catégories
        if (!empty($categories)) {
            wp_set_object_terms($post_id, $categories, 'product_cat');
        }

        // Définir la marque
        if ($brand) {
            wp_set_object_terms($post_id, [$brand], 'product_brand');
        } elseif ($newBrand) {
            $brand_term = wp_insert_term($newBrand, 'product_brand');
            if (!is_wp_error($brand_term)) {
                wp_set_object_terms($post_id, [$brand_term['term_id']], 'product_brand');
            }
        }

        // Définir l'étiquette
        if ($tag) {
            wp_set_object_terms($post_id, [$tag], 'product_tag');
        }

        // Définir l'image principale
        if ($image) {
            set_post_thumbnail($post_id, $image);
        }

        // Définir la galerie d'images
        if (!empty($gallery)) {
            update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery));
        }

        // Traiter les attributs
        if (!empty($raw_attributes)) {
            $attributes = [];
            foreach ($raw_attributes as $attr) {
                if (!empty($attr['name']) && !empty($attr['value'])) {
                    $attribute_slug = wc_sanitize_taxonomy_name($attr['name']);
                    $taxonomy = 'pa_' . $attribute_slug;
                    
                    // Créer la taxonomie si elle n'existe pas
                    if (!taxonomy_exists($taxonomy)) {
                        register_taxonomy(
                            $taxonomy,
                            'product',
                            [
                                'label' => ucfirst($attr['name']),
                                'public' => false,
                                'hierarchical' => false
                            ]
                        );
                    }

                    // Ajouter la valeur à la taxonomie
                    $term = term_exists($attr['value'], $taxonomy);
                    if (!$term) {
                        $term = wp_insert_term($attr['value'], $taxonomy);
                    }
                    
                    if (!is_wp_error($term)) {
                        wp_set_object_terms($post_id, [$term['term_id']], $taxonomy);
                    }

                    // Ajouter l'attribut à la liste des attributs du produit
                    $attributes[$taxonomy] = [
                        'name' => $taxonomy,
                        'value' => '',
                        'is_visible' => 1,
                        'is_variation' => 0,
                        'is_taxonomy' => 1,
                    ];
                }
            }

            if (!empty($attributes)) {
                update_post_meta($post_id, '_product_attributes', $attributes);
            }
        }

        // Add the product to the order if order_id is provided
        $order_id = intval($_POST['order_id'] ?? 0);
        if ($order_id) {
            error_log('Order ID received: ' . $order_id . '. Attempting to add product ' . $post_id . ' to order.');
            $order = wc_get_order($order_id);
            if ($order) {
                $product = wc_get_product($post_id);
                if ($product) {
                    $order->add_product($product, 1); // Add one quantity of the product
                    $order->calculate_totals();
                    $order->save();
                }
            }
        }

        wp_send_json_success(['id' => $post_id, 'name' => $name]);

    } catch (Exception $e) {
        error_log('Error in create_ghost_product AJAX: ' . $e->getMessage());
        wp_send_json_error("Une erreur inattendue s'est produite: " . $e->getMessage());
    }
});

// Fonction AJAX pour créer une nouvelle marque
add_action('wp_ajax_create_product_brand', function() {
    check_ajax_referer('create_brand_nonce', 'security');
    
    $brand_name = sanitize_text_field($_POST['brand_name'] ?? '');
    if (!$brand_name) wp_send_json_error('Nom de marque requis');

    $term = wp_insert_term($brand_name, 'product_brand');
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }

    wp_send_json_success(['term_id' => $term['term_id']]);
});

// Fonction AJAX pour récupérer les valeurs d'un attribut
add_action('wp_ajax_get_attribute_values', function() {
    check_ajax_referer('get_attribute_values_nonce', 'security');
    
    $attribute_name = sanitize_text_field($_POST['attribute_name'] ?? '');
    if (!$attribute_name) wp_send_json_error('Nom d\'attribut requis');

    $taxonomy = 'pa_' . $attribute_name;
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false
    ]);

    $options = '<option value="">Sélectionner une valeur</option>';
    foreach ($terms as $term) {
        $options .= sprintf(
            '<option value="%s">%s</option>',
            esc_attr($term->term_id),
            esc_html($term->name)
        );
    }

    wp_send_json_success(['options' => $options]);
});

// Fonction AJAX pour créer une nouvelle valeur d'attribut
add_action('wp_ajax_create_attribute_value', function() {
    check_ajax_referer('create_attribute_value_nonce', 'security');
    
    $attribute_name = sanitize_text_field($_POST['attribute_name'] ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');
    
    if (!$attribute_name || !$value) {
        wp_send_json_error('Attribut et valeur requis');
    }

    $taxonomy = 'pa_' . $attribute_name;
    $term = wp_insert_term($value, $taxonomy);
    
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }

    wp_send_json_success(['term_id' => $term['term_id']]);
});

// 5. Ajouter une colonne "Ghost" dans Produits
add_filter('manage_edit-product_columns', function ($columns) {
    $columns['ghost_status'] = 'Ghost';
    return $columns;
});

add_action('manage_product_posts_custom_column', function ($column, $post_id) {
    if ($column === 'ghost_status') {
        $visibility = get_post_meta($post_id, '_catalog_visibility', true);
        echo $visibility === 'hidden' ? '<span style="color:red;">Oui</span>' : '<span style="color:green;">Non</span>';
        echo '<br><a href="#" onclick="toggleGhostStatus(' . $post_id . '); return false;">Basculer</a>';
    }
}, 10, 2);

// 6. JS inline pour AJAX Toggle
add_action('admin_footer-edit.php', function () {
    global $typenow;
    if ($typenow !== 'product') return;
    ?>
    <script>
    function toggleGhostStatus(postId) {
        const data = {
            action: 'toggle_ghost_visibility',
            post_id: postId,
            security: '<?php echo wp_create_nonce("toggle_ghost_nonce"); ?>'
        };

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert('Erreur : ' + res.data);
            }
        });
    }
    </script>
    <?php
});

// 7. Action AJAX pour basculer _catalog_visibility
add_action('wp_ajax_toggle_ghost_visibility', function () {
    check_ajax_referer('toggle_ghost_nonce', 'security');

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id || get_post_type($post_id) !== 'product') wp_send_json_error("Produit invalide.");

    $current = get_post_meta($post_id, '_catalog_visibility', true);
    $new = $current === 'hidden' ? 'visible' : 'hidden';

    update_post_meta($post_id, '_catalog_visibility', $new);

    wp_send_json_success(['new' => $new]);
});


// 8. Ajout de style pour colonne "Ghost"
add_action('admin_head', function () {
    echo '<style>
        .column-ghost_status { width: 100px; text-align: center; }
        .ghost-icon { font-weight: bold; font-size: 14px; }
        .ghost-icon.hidden { color: red; }
        .ghost-icon.visible { color: green; }
    </style>';
});

// 9. Ajout d'un filtre "Ghost Products"
add_action('restrict_manage_posts', function () {
    global $typenow;
    if ($typenow !== 'product') return;

    $selected = $_GET['ghost_filter'] ?? '';
    echo '<select name="ghost_filter">
        <option value="">— Filtrer Ghost —</option>
        <option value="yes" ' . selected($selected, 'yes', false) . '>Seulement Ghost</option>
        <option value="no" ' . selected($selected, 'no', false) . '>Sans Ghost</option>
    </select>';
});

// 10. Filtrage des produits via _catalog_visibility
add_filter('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $post_type = $query->get('post_type');
    if ($post_type !== 'product') return;

    $ghost_filter = $_GET['ghost_filter'] ?? '';
    if ($ghost_filter === 'yes') {
        $query->set('meta_query', [[
            'key' => '_catalog_visibility',
            'value' => 'hidden'
        ]]);
    } elseif ($ghost_filter === 'no') {
        $query->set('meta_query', [[
            'key' => '_catalog_visibility',
            'value' => 'hidden',
            'compare' => '!='
        ]]);
    }
});


/******************/
// 1. Affiche le menu déroulant des méthodes dans l'admin commande
add_action('woocommerce_admin_order_data_after_shipping_address', function ($order) {
    $shipping_methods = get_shipping_methods_for_order($order);
    $selected_method_id = get_post_meta($order->get_id(), '_custom_shipping_method_selected', true);
    $order_id = $order->get_id();



    echo '<p class="form-field form-field-wide">';
    echo '<label for="custom_shipping_method">Méthode de livraison :</label>';
    echo '<select name="custom_shipping_method" id="custom_shipping_method" data-order-id="' . esc_attr($order_id) . '">';
    echo '<option value="">-- Choisir une méthode --</option>';

    foreach ($shipping_methods as $method_id => $method_label) {
        echo '<option value="' . esc_attr($method_id) . '" ' . selected($selected_method_id, $method_id, false) . '>' . esc_html($method_label) . '</option>';
    }

    echo '</select></p>';


        $nonce = wp_create_nonce('refresh_shipping_method_selector');
    // Injecter le JavaScript
    ?>
    <script type="text/javascript">
jQuery(document).ready(function($) {
    $('#custom_shipping_method').on('change', function() {
        var method = $(this).val();
        var orderId = $(this).data('order-id');

        $.post(ajaxurl, {
            action: 'save_and_apply_custom_shipping_method',
            order_id: orderId,
            custom_shipping_method: method,
            _wpnonce: '<?php echo wp_create_nonce('save_and_apply_custom_shipping_method'); ?>'
        }, function(response) {
            if (response.success) {
                console.log('Méthode de livraison mise à jour avec succès.');
                
                // Trigger an event to refresh order totals display
                // Replacing: jQuery('.woocommerce_order_items').trigger('change');
                // Simulate click on the standard calculate button to refresh totals display
                const btn = jQuery('.calculate-action');
                if (btn.length) {
                     // Re-enable button if it's disabled by WooCommerce
                     if (btn.is(':disabled')) {
                          btn.prop('disabled', false);
                         }
                         btn.trigger('click');
                    }

            } else {
                console.error('Erreur lors de la mise à jour :', response.data);
                alert('Erreur : ' + response.data);
            }
        });
    });
    console.log('Script de rafraîchissement des méthodes de livraison chargé.');
            function refreshShippingSelector() {
                const orderId = <?php echo (int) $post->ID; ?>;

                $.post(ajaxurl, {
                    action: 'refresh_shipping_method_selector',
                    order_id: orderId,
                    _wpnonce: '<?php echo esc_js($nonce); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#custom_shipping_method').html(response.data.options_html);
                    } else {
                        console.error('Erreur AJAX :', response.data);
                    }
                });
            }

            // Liste des champs d'adresse à surveiller
            const addressFields = [
                'input[name="_shipping_country"]',
                'input[name="_shipping_state"]',
                'input[name="_shipping_postcode"]',
                'input[name="_shipping_city"]',
                'input[name="_shipping_address_1"]'
            ];

            // Rafraîchit le sélecteur à la modification
            $(document).on('change', addressFields.join(','), function () {
                console.log('Changement détecté dans les champs d\'adresse.');
                setTimeout(refreshShippingSelector, 500);
            });
});
</script>
    <?php
});
add_action('wp_ajax_save_and_apply_custom_shipping_method', function () {
    check_ajax_referer('save_and_apply_custom_shipping_method');

    $order_id = intval($_POST['order_id'] ?? 0);
    $method_id = sanitize_text_field($_POST['custom_shipping_method'] ?? '');

    if (!$order_id || !$method_id) {
        wp_send_json_error('ID de commande ou méthode invalide.');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Commande introuvable.');
    }

    update_post_meta($order_id, '_custom_shipping_method_selected', $method_id);
    update_post_meta($order_id, '_custom_shipping_method_changed_flag', 'yes');

    // Obtenir les méthodes disponibles
    $shipping_methods = get_shipping_methods_for_order($order, true);
    if (!isset($shipping_methods[$method_id])) {
        wp_send_json_error('Méthode de livraison non disponible pour cette commande.');
    }

    $method = $shipping_methods[$method_id];
    $cost = 0;
    if ($method instanceof WC_Shipping_Method) {
        // Pour les instances standard de WC_Shipping_Method
        $cost = $method->get_instance_option('cost') ?: 0;
    } elseif (is_object($method) && property_exists($method, 'cost')) {
        // Pour nos objets stdClass personnalisés (méthodes GLS simplifiées)
        $cost = $method->cost ?: 0; // Accéder directement à la propriété cost
    }

    // Supprimer les anciennes lignes de livraison
    foreach ($order->get_items('shipping') as $item_id => $item) {
        $order->remove_item($item_id);
    }

    // Ajouter la nouvelle méthode
    $shipping_item = new WC_Order_Item_Shipping();

    // Remplacer la ligne $shipping_item->set_method_title($method->get_title());
    $method_title = '';
    if ($method instanceof WC_Shipping_Method) {
        $method_title = $method->get_title();
    } elseif (is_object($method) && property_exists($method, 'method_title')) {
        $method_title = $method->method_title;
    }
    $shipping_item->set_method_title($method_title);

    $shipping_item->set_method_id($method_id);
    $shipping_item->set_total($cost);
    $shipping_item->set_order_id($order_id);

    $order->add_item($shipping_item);
    $order->calculate_totals();
    $order->save();

    wp_send_json_success();
});

add_action('wp_ajax_save_custom_shipping_method', function () {
    check_ajax_referer('save_custom_shipping_method');

    $order_id = intval($_POST['order_id'] ?? 0);
    $method = sanitize_text_field($_POST['custom_shipping_method'] ?? '');

    if (!$order_id || !$method) {
        wp_send_json_error('ID de commande ou méthode manquant.');
    }

    update_post_meta($order_id, '_custom_shipping_method_selected', $method);
    update_post_meta($order_id, '_custom_shipping_method_changed_flag', 'yes');

    wp_send_json_success();
});

// 2. Sauvegarde de la méthode sélectionnée
add_action('woocommerce_process_shop_order_meta', function ($order_id) {
    if (!isset($_POST['custom_shipping_method'])) return;
    update_post_meta($order_id, '_custom_shipping_method_selected', sanitize_text_field($_POST['custom_shipping_method']));
    update_post_meta($order_id, '_custom_shipping_method_changed_flag', 'yes');
});

// 3. Appliquer la méthode de livraison sélectionnée
add_action('woocommerce_process_shop_order_meta', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $selected_method_id = isset($_POST['custom_shipping_method']) ? sanitize_text_field($_POST['custom_shipping_method']) : '';
    if (!$selected_method_id) return;

    update_post_meta($order_id, '_custom_shipping_method_selected', $selected_method_id);

    // Charger les méthodes de livraison disponibles
    $shipping_methods = get_shipping_methods_for_order($order, true);
    if (!isset($shipping_methods[$selected_method_id])) return;

    $method = $shipping_methods[$selected_method_id];
    $cost = 0;
    if ($method instanceof WC_Shipping_Method) {
        // Pour les instances standard de WC_Shipping_Method
        $cost = $method->get_instance_option('cost') ?: 0;
    } elseif (is_object($method) && property_exists($method, 'cost')) {
        // Pour nos objets stdClass personnalisés (méthodes GLS simplifiées)
        $cost = $method->cost ?: 0; // Accéder directement à la propriété cost
    }

    // Supprimer les anciennes lignes de livraison
    foreach ($order->get_items('shipping') as $item_id => $item) {
        $order->remove_item($item_id);
    }

    // Ajouter la nouvelle méthode
    $shipping_item = new WC_Order_Item_Shipping();

    // Remplacer la ligne $shipping_item->set_method_title($method->get_title());
    $method_title = '';
    if ($method instanceof WC_Shipping_Method) {
        $method_title = $method->get_title();
    } elseif (is_object($method) && property_exists($method, 'method_title')) {
        $method_title = $method->method_title;
    }
    $shipping_item->set_method_title($method_title);

    $shipping_item->set_method_id($selected_method_id);
    $shipping_item->set_total($cost);
    $shipping_item->set_order_id($order_id);

    $order->add_item($shipping_item);
    $order->calculate_totals();
    $order->save();
});

// 4. Récupérer les méthodes de livraison possibles selon l'adresse de la commande
function get_shipping_methods_for_order($order, $return_objects = false) {
    $country  = $order->get_shipping_country();
    $state    = $order->get_shipping_state();
    $postcode = $order->get_shipping_postcode();
    $city     = $order->get_shipping_city();
    $address  = $order->get_shipping_address_1();

    if (!$country) return [];

    $package = [
        'destination' => [
            'country'  => $country,
            'state'    => $state,
            'postcode' => $postcode,
            'city'     => $city,
            'address'  => $address,
        ],
        'contents'        => [],
        'contents_cost'   => $order->get_subtotal(),
        'applied_coupons' => [],
        'user'            => ['ID' => $order->get_customer_id()]
    ];

    $output = [];
    $available_methods = [];

    // 1. Get methods from standard WooCommerce Shipping Zones
    $zone = WC_Shipping_Zones::get_zone_matching_package($package);
    $standard_methods = $zone->get_shipping_methods(true);
    
    foreach ($standard_methods as $method_instance) {
        $method_id = $method_instance->id;
        $available_methods[$method_id] = $method_instance;
    }

    // 2. Get GLS methods if the class and property exist
    if (class_exists('WC_Gls') && property_exists('WC_Gls', 'carrier_definition')) {
        $gls_carrier_definitions = WC_Gls::$carrier_definition;

        foreach ($gls_carrier_definitions as $gls_id => $gls_definition) {
            // Add to available methods list. For $return_objects=true, we just add a minimal object.
            if ($return_objects) {
                // Calculate simplified cost for GLS method
                $simplified_cost = calculate_gls_rate_simplified($gls_id, $order);

                // Create a minimal object structure for consistency
                $available_methods[$gls_id] = (object) [
                    'id' => $gls_id,
                    'method_title' => $gls_definition['public_name'] ?? $gls_definition['name'], // Use public_name if available
                    'instance_id' => '', // No instance ID for these static definitions
                    'cost' => $simplified_cost, // Use the simplified calculated cost
                    'get_title' => function() use ($gls_definition) { return $gls_definition['public_name'] ?? $gls_definition['name']; },
                    'get_instance_option' => function($option) use ($simplified_cost) { return $option === 'cost' ? $simplified_cost : null; } // Return simplified cost for 'cost' option
                ];
            } else {
                // Add to output for the dropdown
                $output[$gls_id] = $gls_definition['public_name'] ?? $gls_definition['name']; // Use public_name if available
            }
        }
    }

    // Combine methods if $return_objects is false
    if (!$return_objects) {
         // We already populated $output directly in the GLS loop
         // Need to add standard methods to $output if they are not already there
         foreach($standard_methods as $method_instance) {
             $method_id = $method_instance->id;
             // Check if a GLS method with the same ID already added rates (unlikely but safe)
             if (!isset($output[$method_id])) {
                  $output[$method_id] = $method_instance->get_title();
             }
         }
         return $output;
    } else {
        // $available_methods contains instances (or dummy objects) from both standard and GLS methods
        return $available_methods;
    }
}

add_action('wp_ajax_refresh_shipping_method_selector', function () {
    check_ajax_referer('refresh_shipping_method_selector');

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error('Commande introuvable.');
    }

    $methods = get_shipping_methods_for_order($order);
    $selected = get_post_meta($order_id, '_custom_shipping_method_selected', true);

    ob_start();
    echo '<option value="">-- Choisir une méthode --</option>';
    foreach ($methods as $id => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($id),
            selected($selected, $id, false),
            esc_html($label)
        );
    }
    $html = ob_get_clean();

    wp_send_json_success(['options_html' => $html]);
});

// Nouvelle fonction pour calculer un tarif GLS simplifié
function calculate_gls_rate_simplified($method_id, $order) {
    error_log("GLS Simplified Rate: Calculating for method ID: " . $method_id . " and Order ID: " . $order->get_id());

    // 1. Récupérer les options de zones et de tarifs pour cette méthode GLS
    $zones_option_key = $method_id . '_zones';
    $table_rates_option_key = $method_id . '_table_rates';

    error_log("GLS Simplified Rate: Zones option key: " . $zones_option_key);
    error_log("GLS Simplified Rate: Table rates option key: " . $table_rates_option_key);

    $zones = get_option($zones_option_key, []);
    $table_rates = get_option($table_rates_option_key, []);

    error_log("GLS Simplified Rate: Retrieved Zones: " . print_r($zones, true));
    error_log("GLS Simplified Rate: Retrieved Table Rates: " . print_r($table_rates, true));

    if (empty($zones) || empty($table_rates)) {
        error_log("GLS Simplified Rate: No zones or table rates found for " . $method_id);
        return 0;
    }

    // 2. et 3. Extraire les infos de destination et créer un package simplifié
    $package = [
        'destination' => [
            'country'  => $order->get_shipping_country(),
            'state'    => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
            'city'     => $order->get_shipping_city(),
            'address'  => $order->get_shipping_address_1(),
        ],
        'contents'        => $order->get_items(), // Passer les items pour calculer le poids/total
        'contents_cost'   => $order->get_subtotal(),
        //'applied_coupons' => [], // Non nécessaire pour le calcul simplifié
        //'user'            => ['ID' => $order->get_customer_id()] // Non nécessaire pour le calcul simplifié
    ];

    // Calculer le poids total du package à partir des items de la commande
    $package_weight = 0;
    foreach ($package['contents'] as $item_id => $item_data) {
        $product = $item_data->get_product();
        if ($product && $product->has_weight()) {
            $package_weight += (float) $product->get_weight() * $item_data->get_quantity();
        }
    }
    $package['weight'] = $package_weight;

    error_log("GLS Simplified Rate: Package destination: " . print_r($package['destination'], true));
    error_log("GLS Simplified Rate: Package weight: " . $package['weight']);
    error_log("GLS Simplified Rate: Package contents cost: " . $package['contents_cost']);

    // 4. Trouver la zone qui correspond à la destination
    $matching_zone_id = null;
    // Prioriser les zones spécifiques sur la zone par défaut (ID 0)
    uasort($zones, function($a, $b) { // Sort zones so default zone (id 0) is last
        if ($a['id'] == '0') return 1;
        if ($b['id'] == '0') return -1;
        return 0;
    });

    foreach ($zones as $zone) {
        // Vérifier si la destination du package correspond à cette zone
        $country_match = empty($zone['country']) || in_array($package['destination']['country'], (array) $zone['country']);
        // Ajoutez ici d'autres vérifications si les zones GLS utilisent état, code postal, etc. dans leurs règles complexes
        // Pour cette version simplifiée, nous nous basons principalement sur le pays.

        if ($country_match) {
             $matching_zone_id = $zone['id'];
             if ($matching_zone_id != '0') { // Stop if we found a specific zone
                 break;
             }
        }
    }

    // Si aucune zone spécifique n'est trouvée, et si la zone par défaut (ID 0) existe, l'utiliser.
    if ($matching_zone_id === null && isset($zones['0'])) {
         // This case is actually handled by the uasort and the loop above,
         // but keeping this check clarifies the logic.
    }

    if ($matching_zone_id === null) {
        error_log("GLS Simplified Rate: No matching zone found for destination.");
        return 0;
    }
    error_log("GLS Simplified Rate: Matching zone ID found: " . $matching_zone_id);

    // 5. Trouver les tarifs associés à la zone trouvée
    $applicable_rates = [];
    foreach ($table_rates as $rate) {
        if ($rate['zone'] == $matching_zone_id && $rate['enabled'] === '1') {
            $applicable_rates[] = $rate;
        }
    }

    if (empty($applicable_rates)) {
        error_log("GLS Simplified Rate: No applicable rates found for zone ID " . $matching_zone_id);
        return 0;
    }
    error_log("GLS Simplified Rate: Applicable rates found: " . count($applicable_rates));

    // 6. Filtrer les tarifs en fonction des conditions (poids ou prix)
    $matching_rates = [];
    $value_to_check = ($applicable_rates[0]['basis'] === 'weight') ? $package['weight'] : $package['contents_cost'];

    foreach ($applicable_rates as $rate) {
        $min = (float) $rate['min'];
        $max = ($rate['max'] === '*') ? INF : (float) $rate['max'];

        if ($value_to_check >= $min && $value_to_check <= $max) {
            $matching_rates[] = $rate;
        }
    }

    if (empty($matching_rates)) {
        error_log("GLS Simplified Rate: No matching rates found based on weight/price conditions.");
        return 0;
    }
    error_log("GLS Simplified Rate: Matching rates based on conditions: " . count($matching_rates));

    // 7. Sélectionner le tarif le moins cher parmi les correspondants
    $cheapest_rate = null;
    foreach ($matching_rates as $rate) {
        $cost = (float) $rate['cost'];
        if ($cheapest_rate === null || $cost < (float) $cheapest_rate['cost']) {
            $cheapest_rate = $rate;
        }
    }

    // 8. Retourner le coût (ajouter potentiellement le handling fee si stocké avec le tarif)
    // Pour cette version simplifiée, on retourne juste le coût du tarif.
    $final_cost = (float) $cheapest_rate['cost'];
    error_log("GLS Simplified Rate: Cheapest rate cost found: " . $final_cost);
    return $final_cost;
}