/**
 * TCM Vendor UI - Frontend JavaScript
 * Category Navigator Component
 */

jQuery(document).ready(function($) {

    // Check if vendor was detected and settings are available
    if (typeof tcmDropdownSettings === 'undefined' || !tcmDropdownSettings.vendorDetected) {
        // Show error alert if vendor detection failed
        if (typeof tcmDropdownSettings !== 'undefined' && tcmDropdownSettings.errorMessage) {
            alert(tcmDropdownSettings.errorMessage);
        }
        console.error('TCM Dropdown Navigator: Vendor detection failed or settings not loaded');
        return;
    }

    // Configuration
    const autoSelectParts = false;
    const rootURL = "https://tcmlimited.com/";

    // Build service URLs from dynamic configuration
    const serviceConfig = tcmDropdownSettings.serviceConfig || {};
    const standardServices = {};

    Object.keys(serviceConfig).forEach(serviceKey => {
        const service = serviceConfig[serviceKey];
        let url = service.url;

        // Handle relative vs absolute URLs
        if (url.startsWith('/') && !url.startsWith('//')) {
            url = rootURL.replace(/\/$/, '') + url;
        }

        standardServices[serviceKey] = url;
    });

    /**
     * Get parts for a given category from dynamic configuration
     */
    function getPartsForCategory(categorySlug) {
        // Find the category in the visible categories data
        const category = tcmDropdownSettings.visibleCategories.find(cat => cat.slug === categorySlug);

        if (!category || !category.parts || !Array.isArray(category.parts)) {
            return [];
        }

        // Extract part names and sort alphabetically
        return category.parts.map(part => part.name).sort();
    }

    // Generate the category data
    const categoryData = {};

    // Get visible product types for this vendor from localized settings
    // tcmDropdownSettings.visibleCategories is an array of category objects with slug, label, order, parts, services
    const productTypes = tcmDropdownSettings.visibleCategories.map(cat => cat.slug);

    // Build category data structure
    productTypes.forEach(productType => {
        // Find the category object to get service settings
        const category = tcmDropdownSettings.visibleCategories.find(cat => cat.slug === productType);

        if (!category) {
            return;
        }

        // Get parts for this product type from dynamic configuration
        const parts = getPartsForCategory(productType);

        // Build options array based on service settings
        let options = [];
        let subcategories = {};

        // Add Consultation if enabled for this category
        if (category.services && category.services.consultation) {
            const consultationLabel = serviceConfig['consultation']?.label || 'Consultation';
            options.push(consultationLabel);
            subcategories["consultation"] = {
                options: [],
                url: standardServices.consultation || standardServices['consultation']
            };
        }

        // Add Maintenance if enabled for this category
        if (category.services && category.services.maintenance) {
            const maintenanceLabel = serviceConfig['maintenance']?.label || 'Maintenance';
            options.push(maintenanceLabel);
            subcategories["maintenance"] = {
                options: [],
                url: standardServices.maintenance || standardServices['maintenance']
            };
        }

        // Add Fleet Management if enabled for this category
        if (category.services && category.services.fleet_management) {
            const fleetLabel = serviceConfig['fleet-management']?.label || 'Fleet Management';
            options.push(fleetLabel);
            subcategories["fleet-management"] = {
                options: [],
                url: standardServices["fleet-management"] || standardServices['fleet-management']
            };
        }

        // Add Parts option only if parts array isn't empty
        if (parts.length > 0) {
            options.push("Parts");
            subcategories["parts"] = {
                options: parts,
                url: "/product-category/" + productType + "-parts"
            };
        }

        categoryData[productType] = {
            options: options,
            subcategories: subcategories
        };
    });

    /**
     * Update Level 2 dropdown based on Level 1 selection
     */
    window.updateLevel2 = function() {
        const level1 = document.getElementById("level1");
        const level2 = document.getElementById("level2");
        const level3 = document.getElementById("level3");

        level2.disabled = !level1.value;
        level3.disabled = true;

        showLevel3Select();

        if (level1.value && categoryData[level1.value]) {
            level2.innerHTML = "<option value=\"\">Select Option</option>";
            categoryData[level1.value].options.forEach(option => {
                const value = option.toLowerCase().replace(/\s+/g, "-");
                level2.innerHTML += "<option value=\"" + value + "\">" + option + "</option>";
            });

            if(autoSelectParts === true) {
                level2.value = "parts";
                updateLevel3();
            }
        }

        updateGoButton();
    };

    /**
     * Show contact message
     */
    function showContactMessage() {
        document.getElementById("contact-message").style.display = "flex";
        document.getElementById("level3-select-container").style.display = "none";
    }

    /**
     * Show service-specific message
     */
    function showServiceMessage(service) {
        const messageContainer = document.getElementById("contact-message");
        const level3Container = document.getElementById("level3-select-container");

        // Get the service configuration
        let serviceUrl = standardServices[service];
        let messageText = "";

        if (service === "consultation") {
            messageText = "Please contact us for assistance";
        } else if (service === "fleet-management") {
            messageText = "View our fleet management services";
        } else if (service === "maintenance") {
            messageText = "Learn about our maintenance programs";
        } else {
            messageText = "Learn more";
        }

        if (serviceUrl) {
            messageContainer.innerHTML = "<a target=\"_blank\" href=\"" + serviceUrl + "\">" + messageText + "</a>";
        } else {
            messageContainer.innerHTML = messageText;
        }

        messageContainer.style.display = "flex";
        level3Container.style.display = "none";
    }

    /**
     * Show Level 3 select dropdown
     */
    function showLevel3Select() {
        document.getElementById("contact-message").style.display = "none";
        document.getElementById("level3-select-container").style.display = "block";
    }

    /**
     * Update Level 3 dropdown based on Level 2 selection
     */
    window.updateLevel3 = function() {
        const level1 = document.getElementById("level1");
        const level2 = document.getElementById("level2");
        const level3 = document.getElementById("level3");

        const subcategory = categoryData[level1.value]?.subcategories[level2.value];

        if (!subcategory) {
            return;
        }

        if (subcategory.options.length === 0) {
            showServiceMessage(level2.value);
        } else {
            showLevel3Select();
            level3.disabled = !level2.value;
            level3.innerHTML = "<option value=\"\">Select Option</option>";
            subcategory.options.forEach(option => {
                level3.innerHTML += "<option value=\"" + option.toLowerCase() + "\">" + option + "</option>";
            });
        }

        updateGoButton();
    };

    /**
     * Update GO button state and URL
     */
    window.updateGoButton = function() {
        const level1 = document.getElementById("level1");
        const level2 = document.getElementById("level2");
        const level3 = document.getElementById("level3");
        const goButton = document.getElementById("go-button");

        const subcategory = categoryData[level1.value]?.subcategories[level2.value];

        if (subcategory) {
            if (subcategory.options.length === 0) {
                goButton.href = subcategory.url;
                goButton.target = "_blank";
                goButton.style.opacity = "1";
            } else if (level3.value) {
                // For parts, use internal URL
                let sanitizedPath = level3.value.toLowerCase().replace(/[^\w\s-]/g, "").replace(/\s+/g, "-");
                let prefix = "";

                if(sanitizedPath.includes("wheels")) {
                    prefix = "wheels/";
                }

                goButton.href = "/product-category/" + prefix + sanitizedPath;
                goButton.removeAttribute("target");
                goButton.style.opacity = "1";
            } else {
                goButton.href = "#";
                goButton.removeAttribute("target");
                goButton.style.opacity = "0.5";
            }
        } else {
            goButton.href = "#";
            goButton.removeAttribute("target");
            goButton.style.opacity = "0.5";
        }
    };

    /**
     * Handle GO button click
     */
    window.handleGoClick = function(e) {
        const goButton = document.getElementById("go-button");
        if (goButton.href === "#" || goButton.href.endsWith("#")) {
            e.preventDefault();
        }
    };

});
