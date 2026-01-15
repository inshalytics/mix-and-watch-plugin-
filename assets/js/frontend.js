(function ($) {
  "use strict";

  /**
   * Mix & Match Frontend - Minimal Version
   */
  class MixAndMatchMinimal {
    constructor() {
      this.config = window.wc_mnm_params || {};
      this.totalItems = 0;
      this.totalPrice = 0;
      this.selectedProducts = {};
      this.isValid = false;

      // Bind methods
      this.init = this.init.bind(this);
      this.updateTotals = this.updateTotals.bind(this);
      this.validateSelection = this.validateSelection.bind(this);
      this.updateDescription = this.updateDescription.bind(this);
      this.handleQuantityChange = this.handleQuantityChange.bind(this);
      this.handleButtonClick = this.handleButtonClick.bind(this);

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
      let newValue = parseInt($input.val()) || 0;

      // Validate maximum if set
      if (this.config.max_qty > 0 && newValue > this.config.max_qty) {
        newValue = this.config.max_qty;
        $input.val(newValue);
      }

      // Validate minimum (can't go below 0)
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
      let newValue = currentValue + 1;

      if (this.config.max_qty > 0 && newValue > this.config.max_qty) {
        newValue = this.config.max_qty;
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

      // Update items count
      if (maxQty > 0) {
        this.$itemsCount.text(`${this.totalItems}/${maxQty} items`);
      } else {
        this.$itemsCount.text(`${this.totalItems} items`);
      }

      // Update price if in per-item mode
      if (this.config.pricing_mode === "per_item" && this.$totalPrice.length) {
        this.$totalPrice.text(wc_price(this.totalPrice));
      }

      // Update description
      this.updateDescription();
    }

    /**
     * Update description message
     */
    updateDescription() {
      const minQty = this.config.min_qty || 0;
      let message = "";
      let className = "";

      if (minQty > 0) {
        if (this.totalItems >= minQty) {
          message =
            this.config.i18n?.selection_complete ||
            "Selection complete. Ready to add to cart.";
          className = "success";
        } else {
          const needed = minQty - this.totalItems;
          message = this.config.i18n?.need_more_items
            ? this.config.i18n.need_more_items.replace("%d", needed)
            : `You have selected ${this.totalItems} items, please select ${needed} more item(s) to continue.`;
          className = "warning";
        }
      } else {
        if (this.totalItems > 0) {
          message =
            this.config.i18n?.selection_complete || "Ready to add to cart.";
          className = "success";
        } else {
          message = this.config.i18n?.select_items || "Please select items.";
          className = "";
        }
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

      // Check minimum quantity
      if (minQty > 0 && this.totalItems < minQty) {
        isValid = false;
      }

      // Check maximum quantity
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
  }

  // Initialize
  $(document).ready(function () {
    if ($(".wc-mnm-container").length && window.wc_mnm_params) {
      window.wc_mnm_instance = new MixAndMatchMinimal();
    }
  });
})(jQuery);
