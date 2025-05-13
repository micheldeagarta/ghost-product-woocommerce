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
    echo '<div class="wrap"><h1>Ghost Products</h1>';
    echo '<form method="get"><input type="hidden" name="post_type" value="product" /><input type="hidden" name="ghost_only" value="1" /></form>';

    $args = [
        'post_type' => 'product',
        'posts_per_page' => 20,
        'meta_query' => [
            [
                'key' => '_catalog_visibility',
                'value' => 'hidden'
            ],
        ],
    ];

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        echo '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<li><a href="' . get_edit_post_link() . '">' . get_the_title() . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Aucun produit fantôme trouvé.</p>';
    }

    wp_reset_postdata();
    echo '</div>';
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
    <div id="ajax-product-response"></div>
    <input type="text" id="ghost_product_name" placeholder="Nom du produit" style="width:100%; margin-bottom:5px;" required />
    <input type="number" id="ghost_product_price" placeholder="Prix" step="0.01" style="width:100%; margin-bottom:5px;" />
    <input type="text" id="ghost_product_category" placeholder="Catégorie (optionnelle)" style="width:100%; margin-bottom:5px;" />
    <input type="text" id="ghost_product_attributes" placeholder="Attributs (clé:valeur;...)" style="width:100%; margin-bottom:5px;" />
    <button type="button" class="button button-primary" onclick="createGhostProduct()">Créer le produit</button>

    <script>
        function createGhostProduct() {
            const name = document.getElementById('ghost_product_name').value;
            const price = document.getElementById('ghost_product_price').value;
            const category = document.getElementById('ghost_product_category').value;
            const attributes = document.getElementById('ghost_product_attributes').value;

            const data = {
                action: 'create_ghost_product',
                name,
                price,
                category,
                attributes,
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
                    document.getElementById('ghost_product_name').value = '';
                    document.getElementById('ghost_product_price').value = '';
                    document.getElementById('ghost_product_category').value = '';
                    document.getElementById('ghost_product_attributes').value = '';
                } else {
                    document.getElementById('ajax-product-response').innerHTML =
                        '<div class="notice notice-error"><p>' + res.data + '</p></div>';
                }
            });
        }
    </script>
    <?php
}

// 4. Traitement AJAX pour créer un produit WooCommerce invisible
add_action('wp_ajax_create_ghost_product', function () {
    check_ajax_referer('create_ghost_product_nonce', 'security');

    $name = sanitize_text_field($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = sanitize_text_field($_POST['category'] ?? '');
    $raw_attributes = sanitize_text_field($_POST['attributes'] ?? '');

    if (!$name) wp_send_json_error("Nom requis.");

    $post_id = wp_insert_post([
        'post_title' => $name,
        'post_type' => 'product',
        'post_status' => 'publish',
        'meta_input' => [
            '_regular_price' => $price,
            '_price' => $price,
            '_catalog_visibility' => 'hidden',
        ]
    ]);

    if (is_wp_error($post_id)) wp_send_json_error("Erreur lors de la création du produit.");

    if ($category) {
        wp_set_object_terms($post_id, [$category], 'product_cat', true);
    }

    if ($raw_attributes) {
        $pairs = explode(';', $raw_attributes);
        $attributes = [];

        foreach ($pairs as $pair) {
            if (strpos($pair, ':') !== false) {
                [$key, $value] = array_map('trim', explode(':', $pair, 2));
                if ($key && $value) {
                    $attribute_slug = wc_sanitize_taxonomy_name($key);

                    if (!taxonomy_exists('pa_' . $attribute_slug)) {
                        register_taxonomy(
                            'pa_' . $attribute_slug,
                            'product',
                            ['label' => ucfirst($key), 'public' => false, 'hierarchical' => false]
                        );
                    }

                    wp_set_object_terms($post_id, [$value], 'pa_' . $attribute_slug, true);

                    $attributes['pa_' . $attribute_slug] = [
                        'name' => 'pa_' . $attribute_slug,
                        'value' => '',
                        'is_visible' => 1,
                        'is_variation' => 0,
                        'is_taxonomy' => 1,
                    ];
                }
            }
        }

        if (!empty($attributes)) {
            update_post_meta($post_id, '_product_attributes', $attributes);
        }
    }

    wp_send_json_success(['id' => $post_id, 'name' => $name]);
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

// 9. Ajout d’un filtre "Ghost Products"
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
