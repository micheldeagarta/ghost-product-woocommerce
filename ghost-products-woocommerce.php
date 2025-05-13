<?php
/*
Plugin Name: Ghost Products WooCommerce
Description: Crée des produits WooCommerce cachés (invisibles sur le front) avec AJAX et ajoute une vue admin dédiée.
Version: 1.1
Author: ChatGPT
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
        });
    </script>
    <?php
}

// 2. Ajouter la metabox sur les commandes WooCommerce
add_action('add_meta_boxes', function () {
    global $post;
    if ($post && $post->post_type === 'shop_order') {
        add_meta_box(
            'quick_create_product',
            'Créer un produit fantôme (AJAX)',
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
    <button type="button" class="button button-primary" onclick="openGhostProductModal()">Créer un produit fantôme</button>

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
                    <input type="text" id="ghost_product_name" placeholder="Nom du produit" style="width:100%; margin-bottom:5px;" required />
                </div>

                <div class="ghost-product-field">
                    <label for="ghost_product_price">Prix *</label>
                    <input type="number" id="ghost_product_price" placeholder="Prix" step="0.01" style="width:100%; margin-bottom:5px;" required />
                </div>

                <!-- Taxes -->
                <div class="ghost-product-field">
                    <label for="ghost_product_tax_class">Classe de taxe</label>
                    <select id="ghost_product_tax_class" style="width:100%; margin-bottom:5px;">
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
                        <select id="ghost_product_category" class="wc-enhanced-select" multiple="multiple" style="width:100%; margin-bottom:5px;">
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
                        <select id="ghost_product_brand" class="wc-enhanced-select" style="width:100%; margin-bottom:5px;">
                            <option value="">Sélectionner une marque</option>
                            <?php
                            $brands = get_terms('product_brand', array('hide_empty' => false));
                            foreach ($brands as $brand) {
                                echo '<option value="' . esc_attr($brand->term_id) . '">' . esc_html($brand->name) . '</option>';
                            }
                            ?>
                        </select>
                        <input type="text" id="ghost_new_brand" placeholder="Ou créer une nouvelle marque" style="width:100%; margin-top:5px;" />
                    </div>
                </div>

                <!-- Étiquette -->
                <div class="ghost-product-field">
                    <label for="ghost_product_tag">Étiquette</label>
                    <input type="text" id="ghost_product_tag" placeholder="Étiquette" style="width:100%; margin-bottom:5px;" />
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
                            <select class="ghost-attribute-select" style="width:45%;">
                                <option value="">Sélectionner un attribut</option>
                                <?php
                                $attribute_taxonomies = wc_get_attribute_taxonomies();
                                foreach ($attribute_taxonomies as $tax) {
                                    echo '<option value="' . esc_attr($tax->attribute_name) . '">' . esc_html($tax->attribute_label) . '</option>';
                                }
                                ?>
                            </select>
                            <select class="ghost-attribute-value-select" style="width:45%;">
                                <option value="">Sélectionner une valeur</option>
                            </select>
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
                placeholder: 'Sélectionner une classe de taxe',
                allowClear: true
            });

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
                        }
                    });
                } else {
                    valueSelect.hide();
                    valueNew.hide();
                }
            }

            // Gérer les attributs
            $(document).on('change', '.ghost-attribute-select', function() {
                const valueSelect = $(this).siblings('.ghost-attribute-value-select');
                const valueNew = $(this).siblings('.ghost-attribute-value-new');
                
                // Détruire l'instance Select2 existante si elle existe
                if (valueSelect.data('select2')) {
                    valueSelect.select2('destroy');
                }
                
                loadAttributeValues($(this), valueSelect, valueNew);
            });

            // Gérer la création de nouvelle valeur d'attribut
            $(document).on('keypress', '.ghost-attribute-value-new', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const attributeName = $(this).siblings('.ghost-attribute-select').val();
                    const newValue = $(this).val();
                    if (attributeName && newValue) {
                        $.post(ajaxurl, {
                            action: 'create_attribute_value',
                            attribute_name: attributeName,
                            value: newValue,
                            security: '<?php echo wp_create_nonce("create_attribute_value_nonce"); ?>'
                        }, function(response) {
                            if (response.success) {
                                const valueSelect = $('.ghost-attribute-value-select');
                                const option = new Option(newValue, response.data.term_id, true, true);
                                valueSelect.append(option).trigger('change');
                                $('.ghost-attribute-value-new').val('').hide();
                                valueSelect.show();
                            }
                        });
                    }
                }
            });
        });

        // Fonction pour ouvrir la modale
        function openGhostProductModal() {
            document.getElementById('ghost-product-modal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Fonction pour fermer la modale
        function closeGhostProductModal() {
            document.getElementById('ghost-product-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Fermer la modale en cliquant sur le X
        document.querySelector('.ghost-modal-close').onclick = closeGhostProductModal;

        // Fermer la modale en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('ghost-product-modal');
            if (event.target == modal) {
                closeGhostProductModal();
            }
        }

        // Fonction pour ajouter un nouveau champ d'attribut
        window.addGhostAttribute = function() {
            const container = document.getElementById('ghost_attributes_container');
            const newRow = document.createElement('div');
            newRow.className = 'ghost-attribute-row';
            newRow.innerHTML = `
                <select class="ghost-attribute-select" style="width:45%;">
                    <option value="">Sélectionner un attribut</option>
                    <?php
                    $attribute_taxonomies = wc_get_attribute_taxonomies();
                    foreach ($attribute_taxonomies as $tax) {
                        echo '<option value="' . esc_attr($tax->attribute_name) . '">' . esc_html($tax->attribute_label) . '</option>';
                    }
                    ?>
                </select>
                <select class="ghost-attribute-value-select" style="width:45%;">
                    <option value="">Sélectionner une valeur</option>
                </select>
                <input type="text" class="ghost-attribute-value-new" placeholder="Nouvelle valeur" style="display:none; width:45%;" />
                <button type="button" class="button" onclick="this.parentElement.remove()">-</button>
            `;
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
        function ghostUploadImage(type) {
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
                        `<img src="${attachments[0].sizes.thumbnail.url}" class="ghost-image-preview" />`;
                } else {
                    const galleryIds = attachments.map(img => img.id);
                    document.getElementById('ghost_product_gallery').value = galleryIds.join(',');
                    document.getElementById('ghost_gallery_preview').innerHTML = attachments
                        .map(img => `<img src="${img.sizes.thumbnail.url}" class="ghost-image-preview" />`)
                        .join('');
                }
            });

            frame.open();
        }

        // Fonction pour créer le produit
        function createGhostProduct() {
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
                security: '<?php echo wp_create_nonce("create_ghost_product_nonce"); ?>'
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
                    document.getElementById('ghost_attributes_container').innerHTML = `
                        <div class="ghost-attribute-row">
                            <select class="ghost-attribute-select" style="width:45%;">
                                <option value="">Sélectionner un attribut</option>
                                <?php
                                $attribute_taxonomies = wc_get_attribute_taxonomies();
                                foreach ($attribute_taxonomies as $tax) {
                                    echo '<option value="' . esc_attr($tax->attribute_name) . '">' . esc_html($tax->attribute_label) . '</option>';
                                }
                                ?>
                            </select>
                            <select class="ghost-attribute-value-select" style="width:45%;">
                                <option value="">Sélectionner une valeur</option>
                            </select>
                            <input type="text" class="ghost-attribute-value-new" placeholder="Nouvelle valeur" style="display:none; width:45%;" />
                            <button type="button" class="button" onclick="addGhostAttribute()">+</button>
                        </div>
                    `;
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
                } else {
                    document.getElementById('ajax-product-response').innerHTML =
                        '<div class="notice notice-error"><p>' + res.data + '</p></div>';
                }
            });
        }
    </script>
    <?php
}

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

        wp_send_json_success(['id' => $post_id, 'name' => $name]);

    } catch (Exception $e) {
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
