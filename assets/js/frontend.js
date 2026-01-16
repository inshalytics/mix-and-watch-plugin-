/**
 * Mix & Match Products - Premium Frontend JavaScript
 * Theme-adaptive with smooth animations and enhanced UX
 */
(function ($) {
  "use strict";

  /**
   * Theme Color Detector
   * Automatically detects theme colors and adapts the plugin styling
   */
  class ThemeColorDetector {
    constructor() {
      this.colors = {};
      this.init();
    }

    init() {
      this.detectColors();
      this.applyThemeColors();
    }

    /**
     * Detect theme colors from various sources
     */
    detectColors() {
      const $body = $('body');
      const $button = $('.single_add_to_cart_button, .button.alt, .button.primary').first();
      const $price = $('.price, .woocommerce-Price-amount').first();
      
      // Detect primary color from buttons
      if ($button.length) {
        const buttonColor = this.getComputedColor($button[0], 'background-color') || 
                           this.getComputedColor($button[0], 'border-color');
        if (buttonColor) {
          this.colors.primary = this.hexToRgb(buttonColor);
        }
      }

      // Detect accent color from prices
      if ($price.length) {
        const priceColor = this.getComputedColor($price[0], 'color');
        if (priceColor) {
          this.colors.accent = this.hexToRgb(priceColor);
        }
      }

      // Detect background color
      const bgColor = this.getComputedColor(document.body, 'background-color');
      if (bgColor) {
        this.colors.background = this.hexToRgb(bgColor);
      }

      // Detect text color
      const textColor = this.getComputedColor(document.body, 'color');
      if (textColor) {
        this.colors.text = this.hexToRgb(textColor);
      }

      // Fallback to WooCommerce CSS variables if available
      this.detectCSSVariables();
    }

    /**
     * Get computed color from element
     */
    getComputedColor(element, property) {
      if (!element) return null;
      const styles = window.getComputedStyle(element);
      const color = styles.getPropertyValue(property) || styles[property];
      return color ? color.trim() : null;
    }

    /**
     * Convert hex/rgb to RGB object
     */
    hexToRgb(color) {
      if (!color) return null;
      
      // Handle rgb/rgba
      if (color.startsWith('rgb')) {
        const matches = color.match(/\d+/g);
        if (matches && matches.length >= 3) {
          return {
            r: parseInt(matches[0]),
            g: parseInt(matches[1]),
            b: parseInt(matches[2])
          };
        }
      }
      
      // Handle hex
      if (color.startsWith('#')) {
        const hex = color.slice(1);
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        return { r, g, b };
      }
      
      return null;
    }

    /**
     * Detect CSS variables
     */
    detectCSSVariables() {
      const root = document.documentElement;
      const vars = [
        '--woocommerce-color-primary',
        '--woocommerce-color-price',
        '--woocommerce-color-text',
        '--woocommerce-border-color',
        '--woocommerce-background-color'
      ];

      vars.forEach(variable => {
        const value = getComputedStyle(root).getPropertyValue(variable);
        if (value) {
          const key = variable.replace('--woocommerce-color-', '').replace('--woocommerce-', '');
          this.colors[key] = this.hexToRgb(value.trim());
        }
      });
    }

    /**
     * Apply detected colors to CSS variables
     */
    applyThemeColors() {
      const root = document.documentElement;
      
      if (this.colors.primary) {
        const { r, g, b } = this.colors.primary;
        root.style.setProperty('--mnm-primary', `rgb(${r}, ${g}, ${b})`);
        root.style.setProperty('--mnm-accent', `rgb(${r}, ${g}, ${b})`);
      }

      if (this.colors.accent) {
        const { r, g, b } = this.colors.accent;
        root.style.setProperty('--mnm-accent', `rgb(${r}, ${g}, ${b})`);
      }

      if (this.colors.background) {
        const { r, g, b } = this.colors.background;
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        
        // Always use light backgrounds - never apply dark theme colors
        if (brightness > 128) {
          // Light theme - use detected colors
          root.style.setProperty('--mnm-bg', `rgb(${r}, ${g}, ${b})`);
          root.style.setProperty('--mnm-surface', `rgb(${Math.max(0, r - 5)}, ${Math.max(0, g - 5)}, ${Math.max(0, b - 5)})`);
        } else {
          // Dark background detected - force light colors
          root.style.setProperty('--mnm-bg', '#ffffff');
          root.style.setProperty('--mnm-surface', '#ffffff');
        }
      }
    }
  }

  /**
   * Mix & Match Frontend Controller
   */
  class MixAndMatchController {
    constructor() {
      this.config = window.wc_mnm_params || {};
      this.pricingData = window.wc_mnm_pricing || {};
      this.totalItems = 0;
      this.totalPrice = 0;
      this.addonsPrice = 0;
      this.selectedProducts = {};
      this.isValid = false;
      this.pricingMode = this.config.pricing_mode || "per_item";
      this.basePrice = parseFloat(this.config.base_price) || 0; // FIX: Ensure basePrice is a number
      this.minimumPrice = parseFloat(this.config.minimum_price) || 0; // FIX: Ensure minimumPrice is a number
      this.animationFrame = null;

      // Initialize
      this.init();
    }

    /**
     * Initialize
     */
    init() {
      if (!this.config.product_id) {
        console.error("MNM: No product configuration found");
        return;
      }

      // Debug logging
      console.log('MNM Initialization:', {
        pricingMode: this.pricingMode,
        basePrice: this.basePrice,
        basePriceType: typeof this.basePrice
      });

      // Initialize theme color detection
      this.themeDetector = new ThemeColorDetector();

      // Cache DOM elements
      this.cacheDomElements();
      
      // Set up event listeners
      this.setupEventListeners();
      
      // Initial update with animation
      this.updateTotals(true);
      
      // Add smooth entrance animations
      this.animateEntrance();
    }

    /**
     * Animate entrance of elements - minimal fade in
     */
    animateEntrance() {
      const $products = $('.wc-mnm-child-product');
      $products.each((index, el) => {
        const $el = $(el);
        $el.css({ opacity: 0 });
        
        setTimeout(() => {
          $el.css({
            transition: 'opacity 0.3s ease',
            opacity: 1
          });
        }, index * 30);
      });
    }

    /**
     * Cache DOM elements
     */
    cacheDomElements() {
      this.$itemsCount = $("#wc-mnm-items-count");
      this.$totalPrice = $("#wc-mnm-total-price");
      this.$addonsPrice = $("#wc-mnm-addons-price");
      this.$addonsValue = $("#wc-mnm-addons-value");
      this.$totalSummaryPrice = $("#wc-mnm-total-summary-price");
      this.$description = $("#wc-mnm-description");
      this.$addToCartButton = $(".single_add_to_cart_button");
      this.$mobileTotalPrice = $("#wc-mnm-mobile-total-price");
      this.$progressFill = $("#wc-mnm-progress-fill");
      this.$container = $(".wc-mnm-container");
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
      // Quantity input changes with debounce
      let quantityTimeout;
      $(document).on("input change", ".wc-mnm-quantity-input", (e) => {
        clearTimeout(quantityTimeout);
        quantityTimeout = setTimeout(() => {
          this.handleQuantityChange(e.target);
        }, 150);
      });

      // Plus/minus button clicks with haptic feedback
      $(document).on("click", ".wc-mnm-quantity-btn", (e) => {
        e.preventDefault();
        this.addHapticFeedback(e.target);
        this.handleButtonClick(e.target);
      });

      // Form submission validation
      $(".wc-mnm-form").on("submit", (e) => {
        if (!this.isValid) {
          e.preventDefault();
          this.showValidationError("Please complete your selection before adding to cart.");
        }
      });

      // Keyboard support
      $(document).on("keydown", ".wc-mnm-quantity-input", (e) => {
        if (e.key === "ArrowUp") {
          e.preventDefault();
          this.incrementQuantity(e.target);
        } else if (e.key === "ArrowDown") {
          e.preventDefault();
          this.decrementQuantity(e.target);
        }
      });

      // Product card click to select
      $(document).on("click", ".wc-mnm-child-product", (e) => {
        if (!$(e.target).closest('.wc-mnm-quantity-selector').length) {
          const $product = $(e.currentTarget);
          const $input = $product.find('.wc-mnm-quantity-input');
          if ($input.length && !$input.prop('disabled')) {
            const currentValue = parseInt($input.val()) || 0;
            const newValue = currentValue === 0 ? 1 : 0;
            $input.val(newValue).trigger('change');
          }
        }
      });
    }

    /**
     * Add haptic feedback (subtle animation)
     */
    addHapticFeedback(element) {
      const $el = $(element);
      $el.addClass('haptic');
      setTimeout(() => {
        $el.removeClass('haptic');
      }, 200);
    }

    /**
     * Handle quantity change
     */
    handleQuantityChange(input) {
      const $input = $(input);
      const productId = this.getProductIdFromInput($input);
      const oldValue = this.selectedProducts[productId] || 0;
      let newValue = parseInt($input.val()) || 0;
      const maxQty = this.config.max_qty || 0;
      
      // Calculate current total without this product
      let currentTotal = this.totalItems - oldValue;
      
      // Enforce maximum at container level
      if (maxQty > 0) {
        const maxAllowedForThisProduct = Math.max(0, maxQty - currentTotal);
        
        if (newValue > maxAllowedForThisProduct) {
          newValue = maxAllowedForThisProduct;
          $input.val(newValue);
          
          if (newValue < oldValue) {
            this.showMaxLimitWarning();
          }
        }
      }
      
      // Validate minimum
      if (newValue < 0) {
        newValue = 0;
        $input.val(newValue);
      }
      
      // Update UI state for this product with animation
      this.updateProductState($input, productId, newValue, oldValue);
      
      // Update selected products
      if (newValue > 0) {
        this.selectedProducts[productId] = newValue;
      } else {
        delete this.selectedProducts[productId];
      }
      
      // Update totals and UI
      this.updateTotals();
    }

    /**
     * Update product visual state - minimal
     */
    updateProductState($input, productId, quantity, oldQuantity) {
      const $product = $input.closest(".wc-mnm-child-product");
      
      if (quantity > 0) {
        $product.addClass("selected");
        $input.addClass("has-quantity");
      } else {
        $product.removeClass("selected");
        $input.removeClass("has-quantity");
      }
    }

    /**
     * Handle button clicks
     */
    handleButtonClick(button) {
      const $button = $(button);
      const productId = $button.data("target");
      const $input = $(`#wc-mnm-quantity-${productId}`);

      if (!$input.length) return;

      if ($button.hasClass("increase")) {
        this.incrementQuantity($input);
      } else if ($button.hasClass("decrease")) {
        this.decrementQuantity($input);
      }

      $input.trigger("change");
    }

    /**
     * Increment quantity
     */
    incrementQuantity(input) {
      const $input = $(input);
      const currentValue = parseInt($input.val()) || 0;
      const maxQty = this.config.max_qty || 0;
      
      // Check if we can add more
      if (maxQty > 0 && this.totalItems >= maxQty) {
        this.showMaxLimitWarning();
        return;
      }
      
      let newValue = currentValue + 1;
      
      // Check individual product max (if any)
      const individualMax = parseInt($input.attr("max")) || 0;
      if (individualMax > 0 && newValue > individualMax) {
        newValue = individualMax;
      }
      
      $input.val(newValue).trigger("input");
    }

    /**
     * Decrement quantity
     */
    decrementQuantity(input) {
      const $input = $(input);
      const currentValue = parseInt($input.val()) || 0;
      const newValue = Math.max(0, currentValue - 1);

      $input.val(newValue).trigger("input");
    }

    /**
     * Show max limit warning with animation
     */
    showMaxLimitWarning() {
      const maxQty = this.config.max_qty || 0;
      
      // Create or update warning message
      let $warning = $(".wc-mnm-max-warning");
      
      if (!$warning.length) {
        $warning = $('<div class="wc-mnm-max-warning"></div>');
        $(".wc-mnm-description").after($warning);
      }
      
      $warning.html(`
        <div class="wc-mnm-validation-warning">
          <span class="dashicons dashicons-warning"></span>
          ${this.config.i18n?.max_limit_reached || 
            `Maximum limit reached: You can select up to ${maxQty} items total. 
             To add more items, please reduce quantities of other products.`}
        </div>
      `).hide().slideDown(300);
      
      // Shake animation
      $warning.css('animation', 'shake 0.5s ease');
      
      // Auto-hide after 5 seconds
      setTimeout(() => {
        $warning.slideUp(300, () => {
          $warning.remove();
        });
      }, 5000);
    }

    /**
     * Show validation error
     */
    showValidationError(message) {
      this.$description
        .text(message)
        .removeClass("success warning")
        .addClass("error");
      
      // Scroll to error with smooth animation
      $("html, body").animate(
        {
          scrollTop: this.$description.offset().top - 100,
        },
        400
      );
    }

    /**
     * Update product button states
     */
    updateProductButtonStates() {
      const maxQty = this.config.max_qty || 0;
      const canAddMore = maxQty === 0 || this.totalItems < maxQty;
      
      // Update increase buttons
      $(".wc-mnm-quantity-btn.increase").prop("disabled", !canAddMore);
      
      // Update container state
      if (maxQty > 0 && this.totalItems >= maxQty) {
        this.$container.addClass("max-reached");
      } else {
        this.$container.removeClass("max-reached");
      }
    }

    /**
     * Update totals with smooth animations
     */
    updateTotals(initial = false) {
      let totalItems = 0;
      let addonsPrice = 0;

      // Calculate totals
      Object.keys(this.selectedProducts).forEach((productId) => {
        const quantity = this.selectedProducts[productId];
        const $input = $(`#wc-mnm-quantity-${productId}`);
        const price = parseFloat($input.data("price")) || 0;

        totalItems += quantity;
        addonsPrice += price * quantity;
      });

      this.totalItems = totalItems;
      this.addonsPrice = addonsPrice;
      
      // Debug logging
      console.log('Price Calculation:', {
        basePrice: this.basePrice,
        addonsPrice: this.addonsPrice,
        basePriceType: typeof this.basePrice,
        addonsPriceType: typeof this.addonsPrice
      });
      
      // Calculate total price based on pricing mode
      switch (this.pricingMode) {
        case "fixed":
          this.totalPrice = parseFloat(this.config.fixed_price) || 0;
          break;
        case "base_addon":
          // FIX: Ensure both are numbers before addition
          const base = parseFloat(this.basePrice) || 0;
          const addons = parseFloat(this.addonsPrice) || 0;
          this.totalPrice = base + addons;
          console.log('Base + Addons Calculation:', { base, addons, total: this.totalPrice });
          break;
        case "per_item":
        default:
          this.totalPrice = parseFloat(this.addonsPrice) || 0;
          break;
      }

      // Debug final price
      console.log('Final Price:', {
        totalPrice: this.totalPrice,
        totalPriceType: typeof this.totalPrice
      });

      // Update UI with animation
      if (!initial) {
        this.animatePriceChange();
      }
      this.updateDisplay();
      this.validateSelection();
    }

    /**
     * Price change - no animation, just update
     */
    animatePriceChange() {
      // Minimal - no animations
    }

    /**
     * Update display
     */
    updateDisplay() {
      this.updateItemsCount();
      this.updatePriceDisplay();
      this.updateDescription();
      this.updateProductButtonStates();
      this.updateProgressBar();
    }

    /**
     * Update items count display
     */
    updateItemsCount() {
      const maxQty = this.config.max_qty || 0;
      
      if (maxQty > 0) {
        const percentage = Math.min(100, (this.totalItems / maxQty) * 100);
        this.$itemsCount.html(`
          <span class="wc-mnm-count">${this.totalItems}/${maxQty}</span>
          ${this.totalItems >= maxQty ? 
            '<span class="wc-mnm-limit-reached">(Maximum reached)</span>' : ''}
        `);
      } else {
        this.$itemsCount.text(this.totalItems);
      }
    }

    /**
     * Update price display - minimal, no fade animations
     */
    updatePriceDisplay() {
      switch (this.pricingMode) {
        case "fixed":
          // Fixed price doesn't change
          break;
          
        case "base_addon":
          // Debug before updating
          console.log('Updating Base + Addons Display:', {
            addonsPrice: this.addonsPrice,
            totalPrice: this.totalPrice,
            formattedAddons: this.formatPrice(this.addonsPrice),
            formattedTotal: this.formatPrice(this.totalPrice)
          });
          
          // Update add-ons price in price breakdown
          if (this.$addonsPrice.length) {
            this.$addonsPrice.html(this.formatPrice(this.addonsPrice));
          }
          
          // Update add-ons value in summary (above add to cart)
          if (this.$addonsValue.length) {
            this.$addonsValue.html(this.formatPrice(this.addonsPrice));
          }
          
          // Update total price in price breakdown
          if (this.$totalPrice.length) {
            this.$totalPrice.html(this.formatPrice(this.totalPrice));
          }
          
          // Update total summary price (above add to cart)
          if (this.$totalSummaryPrice.length) {
            this.$totalSummaryPrice.html(this.formatPrice(this.totalPrice));
          }
          
          // Update mobile total price
          if (this.$mobileTotalPrice.length) {
            this.$mobileTotalPrice.html(this.formatPrice(this.totalPrice));
          }
          break;
          
        case "per_item":
        default:
          // Update total price
          if (this.$totalPrice.length) {
            this.$totalPrice.html(this.formatPrice(this.totalPrice));
          }
          
          // Update mobile total price
          if (this.$mobileTotalPrice.length) {
            this.$mobileTotalPrice.html(this.formatPrice(this.totalPrice));
          }
          break;
      }
    }

    /**
     * Update description message
     */
    updateDescription() {
      const minQty = this.config.min_qty || 0;
      let message = "";
      let className = "";

      if (this.totalItems === 0) {
        if (minQty > 0) {
          message = this.config.i18n?.need_more_items
            ? this.config.i18n.need_more_items
                .replace("%d", 0)
                .replace("%d", minQty)
            : `You have selected 0 items, please select ${minQty} items total to continue.`;
        } else {
          message = this.config.i18n?.select_items || "Please select items.";
        }
        className = "";
      } else if (minQty > 0 && this.totalItems < minQty) {
        const needed = minQty - this.totalItems;
        message = this.config.i18n?.need_more_items
          ? this.config.i18n.need_more_items
              .replace("%d", this.totalItems)
              .replace("%d", needed)
          : `You have selected ${this.totalItems} items total, please select ${needed} more item(s).`;
        className = "warning";
      } else if (this.totalItems >= minQty) {
        message =
          this.config.i18n?.selection_complete ||
          `Selected ${this.totalItems} items total. Ready to add to cart.`;
        className = "success";
      }

      this.$description
        .text(message)
        .removeClass("success warning error")
        .addClass(className);
    }

    /**
     * Update progress bar with smooth animation
     */
    updateProgressBar() {
      const maxQty = this.config.max_qty || 0;
      
      if (this.$progressFill.length && maxQty > 0) {
        const percentage = Math.min(100, (this.totalItems / maxQty) * 100);
        this.$progressFill.css("width", percentage + "%");
      }
    }

    /**
     * Format price according to WooCommerce settings
     */
    formatPrice(price) {
      // Ensure price is a number
      const numPrice = parseFloat(price);
      if (isNaN(numPrice)) {
        console.error('Invalid price for formatting:', price);
        return '<span class="woocommerce-Price-amount amount">$0.00</span>';
      }
      
      const symbol = this.config.currency_symbol || "$";
      const position = this.config.currency_position || "left";
      const decimals = this.config.price_decimals || 2;
      const decimalSep = this.config.price_decimal_sep || ".";
      const thousandSep = this.config.price_thousand_sep || ",";
      
      // Format number with proper decimal places
      let formatted = numPrice.toFixed(decimals);
      
      // Replace decimal separator
      if (decimalSep !== ".") {
        formatted = formatted.replace(".", decimalSep);
      }
      
      // Add thousand separators
      if (thousandSep && thousandSep !== "") {
        const parts = formatted.split(decimalSep);
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
        formatted = parts.join(decimalSep);
      }
      
      // Apply currency position
      let result;
      switch (position) {
        case "right":
          result = formatted + symbol;
          break;
        case "left_space":
          result = symbol + " " + formatted;
          break;
        case "right_space":
          result = formatted + " " + symbol;
          break;
        case "left":
        default:
          result = symbol + formatted;
          break;
      }
      
      // Wrap in span for consistency with WooCommerce
      return '<span class="woocommerce-Price-amount amount">' + result + '</span>';
    }

    /**
     * Validate selection
     */
    validateSelection() {
      const minQty = this.config.min_qty || 0;
      const maxQty = this.config.max_qty || 0;
      let isValid = true;

      // Check minimum total quantity
      if (minQty > 0 && this.totalItems < minQty) {
        isValid = false;
      }

      // Check maximum total quantity
      if (maxQty > 0 && this.totalItems > maxQty) {
        isValid = false;
      }

      // Check if any selection is made (when min is 0)
      if (minQty === 0 && this.totalItems === 0) {
        isValid = false;
      }

      // Update state
      this.isValid = isValid;

      // Update button state
      this.updateButtonState();

      return this.isValid;
    }

    /**
     * Update button state - minimal
     */
    updateButtonState() {
      if (this.isValid) {
        this.$addToCartButton
          .prop("disabled", false)
          .removeClass("disabled")
          .addClass("pulse");
      } else {
        this.$addToCartButton
          .prop("disabled", true)
          .addClass("disabled")
          .removeClass("pulse");
      }
    }

    /**
     * Get product ID from input
     */
    getProductIdFromInput($input) {
      const name = $input.attr("name");
      const match = name.match(/\[(\d+)\]/);
      return match ? match[1] : null;
    }

    /**
     * Get current selection summary
     */
    getSelectionSummary() {
      return {
        totalItems: this.totalItems,
        totalPrice: this.totalPrice,
        addonsPrice: this.addonsPrice,
        basePrice: this.basePrice,
        selectedProducts: { ...this.selectedProducts },
        isValid: this.isValid,
        pricingMode: this.pricingMode,
      };
    }

    /**
     * Debug function to check calculations
     */
    debugCalculations() {
      console.log('Debug Calculations:', {
        basePrice: this.basePrice,
        addonsPrice: this.addonsPrice,
        totalPrice: this.totalPrice,
        basePriceParsed: parseFloat(this.basePrice),
        addonsPriceParsed: parseFloat(this.addonsPrice),
        selectedProducts: this.selectedProducts
      });
    }
  }

  // Add shake animation
  const style = document.createElement('style');
  style.textContent = `
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    .wc-mnm-quantity-btn.haptic {
      transform: scale(0.95);
    }
  `;
  document.head.appendChild(style);

  // Initialize when DOM is ready
  $(document).ready(function () {
    // Check if we're on a Mix & Match product page
    if ($(".wc-mnm-container").length && window.wc_mnm_params) {
      window.wc_mnm_instance = new MixAndMatchController();
    }
  });
})(jQuery);