-- // 4 - Post announcement article

INSERT INTO main.news (id, manager_id, title, article, created, published) VALUES (1, 1, 'Announcing release of "Plugin Check"', 'We''re happy to announce that our tool "Plugin Check" is ready for public use.

With "Plugin Check" you can lookup if a plugin you use is compliant with the General Data Protection Regulation or GDPR.

We currently are monitoring 300+ plugins for 3 major e-commerce platforms: Magento, PrestaShop and WooCommerce.

We also publish regular updates on plugins that have become compliant.

Happy checking!', '2017-09-21 13:19:02', '2017-09-21 13:19:02');

-- //@UNDO

DELETE FROM main.news WHERE id = 1;

-- //