(function ($) {
  "use strict";

  /**
   * Mix & Match 
   */
  class MixAndMatchMinimal {
    constructor() {
      this.config = window.wc_mnm_params || {};
      this.totalItems = 0;
      this.totalPrice = 0;
      this.selectedProducts = {};
      this.isValid = false;

      // Store pricing mode
      this.pricingMode = this.config.pricing_mode || "per_item";

      // Bind methods
      this.init = this.init.bind(this);
      this.updateTotals = this.updateTotals.bind(this);
      this.validateSelection = this.validateSelection.bind(this);
      this.updateDescription = this.updateDescription.bind(this);
      this.handleQuantityChange = this.handleQuantityChange.bind(this);
      this.handleButtonClick = this.handleButtonClick.bind(this);
      this.updatePriceDisplay = this.updatePriceDisplay.bind(this);
      this.incrementQuantity = this.incrementQuantity.bind(this);
      this.decrementQuantity = this.decrementQuantity.bind(this);
      this.getProductIdFromInput = this.getProductIdFromInput.bind(this);
      this.updateButtonState = this.updateButtonState.bind(this);

      // Initialize on DOM ready
      $(document).ready(this.init);
    }

    /**
     * Initialize
     */
    init() {
      if (!this.config.product_id) {
        console.error("MNM: No product configuration found");
        return;
      }

      // Cache DOM elements
      this.$itemsCount = $("#wc-mnm-items-count");
      this.$totalPrice = $("#wc-mnm-total-price");
      this.$description = $("#wc-mnm-description");
      this.$addToCartButton = $(".single_add_to_cart_button");

      // Set up event listeners
      this.setupEventListeners();

      // Initial update
      this.updateTotals();

      console.log("MNM: Initialized with pricing mode:", this.pricingMode);
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
      // Quantity input changes
      $(document).on("input change", ".wc-mnm-quantity-input", (e) => {
        this.handleQuantityChange(e.target);
      });

      // Plus/minus button clicks
      $(document).on("click", ".wc-mnm-quantity-btn", (e) => {
        this.handleButtonClick(e.target);
      });

      // Form submission validation
      $(".wc-mnm-form").on("submit", (e) => {
        if (!this.isValid) {
          e.preventDefault();
          this.$description.addClass("warning");
          // Scroll to error
          $("html, body").animate(
            {
              scrollTop: this.$description.offset().top - 100,
            },
            300
          );
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
        // Calculate maximum allowed for this specific product
        const maxAllowedForThisProduct = Math.max(0, maxQty - currentTotal);
        
        if (newValue > maxAllowedForThisProduct) {
            newValue = maxAllowedForThisProduct;
            $input.val(newValue);
            
            // Show warning if trying to exceed max
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
    
    // Update selected products
    if (newValue > 0) {
        this.selectedProducts[productId] = newValue;
    } else {
        delete this.selectedProducts[productId];
    }
    
    // Update UI
    this.updateTotals();
}

/**
 * Show max limit warning
 */
showMaxLimitWarning() {
    const maxQty = this.config.max_qty || 0;
    
    // Create or update warning message
    let $warning = $('.wc-mnm-max-warning');
    
    if (!$warning.length) {
        $warning = $('<div class="wc-mnm-max-warning"></div>');
        $('.wc-mnm-description').after($warning);
    }
    
    $warning.html(`
        <div class="wc-mnm-validation-warning">
            Maximum limit reached: You can select up to ${maxQty} items total.
            To add more items, please reduce quantities of other products.
        </div>
    `).slideDown(300);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        $warning.slideUp(300, () => {
            $warning.remove();
        });
    }, 5000);
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
    const individualMax = parseInt($input.attr('max')) || 0;
    if (individualMax > 0 && newValue > individualMax) {
        newValue = individualMax;
    }
    
    $input.val(newValue).trigger('input');
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
     * Update button states based on availability
     */
    updateProductButtonStates() {
        const maxQty = this.config.max_qty || 0;
        const canAddMore = maxQty === 0 || this.totalItems < maxQty;
        
        // Disable all increase buttons if max reached
        $('.wc-mnm-quantity-btn.increase').prop('disabled', !canAddMore);
        
        // Add visual indicator
        if (maxQty > 0 && this.totalItems >= maxQty) {
            $('.wc-mnm-container').addClass('max-reached');
        } else {
            $('.wc-mnm-container').removeClass('max-reached');
        }
    }

    /**
     * Update totals
     */
    updateTotals() {
      let totalItems = 0;
      let totalPrice = 0;

      // Calculate totals
      Object.keys(this.selectedProducts).forEach((productId) => {
        const quantity = this.selectedProducts[productId];
        const $input = $(`#wc-mnm-quantity-${productId}`);
        const price = parseFloat($input.data("price")) || 0;

        totalItems += quantity;
        totalPrice += price * quantity;
      });

      this.totalItems = totalItems;
      this.totalPrice = totalPrice;

      // Update UI
      this.updateDisplay();
      this.validateSelection();
    }

    /**
     * Update display
     */
    updateDisplay() {
    const maxQty = this.config.max_qty || 0;
    
    // Update items count with progress indicator
    if (maxQty > 0) {
        const percentage = Math.min(100, (this.totalItems / maxQty) * 100);
        this.$itemsCount.html(`
            <span class="wc-mnm-count">${this.totalItems}/${maxQty} items</span>
            ${this.totalItems >= maxQty ? '<span class="wc-mnm-limit-reached">(Maximum reached)</span>' : ''}
        `);
        
        // Update progress indicator if it exists
        const $progress = $('#wc-mnm-progress-fill');
        if ($progress.length) {
            $progress.css('width', percentage + '%');
        }
        } else {
            this.$itemsCount.text(`${this.totalItems} items`);
        }
        
        // Update price if in per-item mode
        if (this.pricingMode === 'per_item' && this.$totalPrice.length) {
            this.updatePriceDisplay();
        }
        
        // Update description
        this.updateDescription();
        
        // Update button states
        this.updateProductButtonStates();
      }

    /**
     * Update price display for per-item mode
     */
    updatePriceDisplay() {
      // Format and update price using WooCommerce's price formatting
      const formattedPrice = this.formatPrice(this.totalPrice);
      this.$totalPrice.text(formattedPrice);
    }

    /**
     * Format price according to WooCommerce settings
     */
    formatPrice(price) {
      const symbol = this.config.currency_symbol || "$";
      const position = this.config.currency_position || "left";
      const formatted = parseFloat(price).toFixed(2);

      switch (position) {
        case "left":
          return symbol + formatted;
        case "right":
          return formatted + symbol;
        case "left_space":
          return symbol + " " + formatted;
        case "right_space":
          return formatted + " " + symbol;
        default:
          return symbol + formatted;
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
                .replace("%d", minQty)
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
     * Update button state
     */
    updateButtonState() {
      if (this.isValid) {
        this.$addToCartButton.prop("disabled", false).removeClass("disabled");
      } else {
        this.$addToCartButton.prop("disabled", true).addClass("disabled");
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
        selectedProducts: { ...this.selectedProducts },
        isValid: this.isValid,
        pricingMode: this.pricingMode,
      };
    }
  }

  // Initialize when DOM is ready
  $(document).ready(function () {
    // Check if we're on a Mix & Match product page
    if ($(".wc-mnm-container").length && window.wc_mnm_params) {
      window.wc_mnm_instance = new MixAndMatchMinimal();
    }
  });
})(jQuery);
