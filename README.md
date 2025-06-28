# Woo Auto Pricing Tool

This plugin adds powerful admin tools to **automatically update product pricing** in WooCommerce, and reset it back to the original values.

## ✨ Features

- **Apply Auto Pricing:** Automatically calculate and set new prices for all products.
- **Reset Pricing:** Restore original prices of products that were updated.
- **Status Column:** Displays whether auto pricing was applied to each product.
- **Exclusion Support:** You can customize which products to exclude.

## 📂 Installation

1. Copy the PHP code into your theme's `functions.php` file or create a custom plugin.
2. Make sure WooCommerce is installed and active.
3. Go to **Products > All Products** in WordPress Admin.
4. Use the new buttons at the top of the product list to apply or reset pricing.

## 🛠 How It Works

- **Apply Auto Pricing:**
  - Loops through all published products.
  - Calculates a new retail price using your custom logic (`mysite_calculate_retail_price`).
  - Saves the new price and marks the product as updated.
- **Reset Pricing:**
  - Finds all products with auto pricing applied.
  - Restores their original price.
  - Removes the auto pricing markers.

## 🔒 Security

- Nonces are used to protect AJAX requests.
- Only users with `manage_woocommerce` capability can apply or reset pricing.

## ⚙️ Customization

You can customize:
- The logic inside `mysite_calculate_retail_price()`.
- Which products are excluded (`mysite_is_product_excluded()`).

Feel free to fork and modify as needed.



