# Manual Custom Shipping

A lightweight WooCommerce plugin that adds per-product custom shipping price and shipping time to each product. 

## Features
- Individual shipping cost for each product multiplied by quantity.
- Separate display of "Shipping Days" on checkout/cart pages.
- Optionally hides itself if no product has a custom shipping cost.

## Installation
1. Download or clone this repo into your `wp-content/plugins/` folder.  
2. Ensure the folder name is `manual-custom-shipping`.
3. Activate the plugin in **WordPress Admin → Plugins**.

## Usage
1. Edit any WooCommerce product, open the **Shipping** tab.
2. Enter your custom shipping price and time in the fields provided.
3. Save the product.
4. The shipping cost (price * quantity) is shown at checkout.
5. The shipping time is displayed below the shipping row, above the total.

## Customizing
- To change how times are displayed, see the code in the last function of `manual-custom-shipping.php`.
- If you want to show or hide certain logic (e.g., numeric ranges vs. textual strings), tweak the logic there.

## Contributing
Feel free to create issues or pull requests on GitHub if you find any bugs or want to add new features.

## License
This plugin is open-sourced under the [MIT License](https://opensource.org/licenses/MIT).
