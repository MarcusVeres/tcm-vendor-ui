/**
 * TCM Vendor UI - Admin JavaScript
 * Handles color pickers and media uploader
 */

jQuery(document).ready(function($) {

    /**
     * Initialize WordPress Color Pickers
     */
    $('.tcm-color-picker').wpColorPicker();

    /**
     * Media Uploader for Logo Images
     */
    $('.tcm-upload-logo').on('click', function(e) {
        e.preventDefault();

        const button = $(this);
        const inputField = button.siblings('.tcm-logo-url');
        const previewContainer = button.siblings('.tcm-logo-preview');

        // Create media uploader
        const mediaUploader = wp.media({
            title: 'Select Logo Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        // When image is selected
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();

            // Set URL in input field
            inputField.val(attachment.url);

            // Update preview
            if (previewContainer.length) {
                previewContainer.find('img').attr('src', attachment.url);
            } else {
                // Create preview if it doesn't exist
                const preview = $('<div class="tcm-logo-preview"><img src="' + attachment.url + '" alt="Logo preview" style="max-width: 100px; max-height: 50px; margin-top: 5px;"></div>');
                button.after(preview);
            }
        });

        // Open media uploader
        mediaUploader.open();
    });

    /**
     * Reset to Defaults Button
     */
    $('.tcm-reset-defaults').on('click', function(e) {
        if (!confirm('Are you sure you want to reset all vendor settings to defaults? This cannot be undone.')) {
            return;
        }

        // Default vendor settings (matching PHP defaults)
        const defaults = {
            'administrator': {
                'name': 'Administrator',
                'logo_url': 'http://shop.tcmlimited.com/wp-content/uploads/2025/03/tcm-square-logo.png',
                'bg_color': '#2A8EBF',
                'text_color': '#ffffff',
                'button_bg': '#0BA04C',
                'button_text': '#ffffff'
            },
            'standard-pricing': {
                'name': 'Standard Pricing',
                'logo_url': 'http://shop.tcmlimited.com/wp-content/uploads/2025/03/tcm-square-logo.png',
                'bg_color': '#0D3692',
                'text_color': '#ffffff',
                'button_bg': '#2A8EBF',
                'button_text': '#ffffff'
            },
            'canadian-tire': {
                'name': 'Canadian Tire',
                'logo_url': 'http://shop.tcmlimited.com/wp-content/uploads/2025/04/e4f3dc6b134edf591489edcaf9c9209c.png',
                'bg_color': '#262626',
                'text_color': '#ffffff',
                'button_bg': '#F4D52D',
                'button_text': '#262626'
            },
            'costco': {
                'name': 'Costco',
                'logo_url': 'http://shop.tcmlimited.com/wp-content/uploads/2025/04/costco.png',
                'bg_color': '#005DAB',
                'text_color': '#ffffff',
                'button_bg': '#E31936',
                'button_text': '#ffffff'
            },
            'loblaws': {
                'name': 'Loblaws',
                'logo_url': 'http://shop.tcmlimited.com/wp-content/uploads/2025/04/loblaws.png',
                'bg_color': '#191919',
                'text_color': '#ffffff',
                'button_bg': '#DC2626',
                'button_text': '#ffffff'
            }
        };

        // Update each vendor's fields
        $.each(defaults, function(slug, settings) {
            // Logo URL
            $('input[name="tcm_vendor_styles[' + slug + '][logo_url]"]').val(settings.logo_url);

            // Update logo preview
            const preview = $('input[name="tcm_vendor_styles[' + slug + '][logo_url]"]')
                .siblings('.tcm-logo-preview')
                .find('img');
            if (preview.length) {
                preview.attr('src', settings.logo_url);
            }

            // Colors - need to update both the input and the color picker
            const bgColorInput = $('input[name="tcm_vendor_styles[' + slug + '][bg_color]"]');
            bgColorInput.val(settings.bg_color).wpColorPicker('color', settings.bg_color);

            const textColorInput = $('input[name="tcm_vendor_styles[' + slug + '][text_color]"]');
            textColorInput.val(settings.text_color).wpColorPicker('color', settings.text_color);

            const buttonBgInput = $('input[name="tcm_vendor_styles[' + slug + '][button_bg]"]');
            buttonBgInput.val(settings.button_bg).wpColorPicker('color', settings.button_bg);

            const buttonTextInput = $('input[name="tcm_vendor_styles[' + slug + '][button_text]"]');
            buttonTextInput.val(settings.button_text).wpColorPicker('color', settings.button_text);
        });

        alert('All vendor settings have been reset to defaults. Click "Save All Vendor Settings" to apply changes.');
    });

    /**
     * Drag-and-Drop Ordering for Categories
     */
    if ($('#tcm-sortable-categories').length) {
        $('#tcm-sortable-categories').sortable({
            handle: '.drag-handle',
            placeholder: 'ui-sortable-placeholder',
            helper: function(e, tr) {
                const $originals = tr.children();
                const $helper = tr.clone();
                $helper.children().each(function(index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            update: function(event, ui) {
                // Update order values when items are reordered
                $('#tcm-sortable-categories tr').each(function(index) {
                    $(this).find('.category-order-input').val(index + 1);
                });
            }
        });
    }

    /**
     * Reset Category Defaults Button
     */
    $('.tcm-reset-category-defaults').on('click', function(e) {
        if (!confirm('Are you sure you want to reset all category labels to defaults? This cannot be undone.')) {
            return;
        }

        // Default category labels (matching PHP defaults)
        const defaults = {
            'bakery-carts': 'Bakery Carts',
            'cart-models': 'Cart Models',
            'cart-pushers': 'Cart Pushers',
            'cart-washers': 'Cart Washers',
            'corral-carts': 'Corral Carts',
            'parts-accessories': 'Parts & Accessories',
            'safety-vests': 'Safety Vests',
            'shopping-baskets': 'Shopping Baskets',
            'winter-salt': 'Winter Salt'
        };

        // Update each category label
        $.each(defaults, function(slug, label) {
            $('input[name="tcm_dropdown_categories[' + slug + '][label]"]').val(label);
        });

        alert('All category labels have been reset to defaults. Click "Save Category Settings" to apply changes.');
    });

});
