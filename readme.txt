=== Just another Sape.ru integration ===
Contributors: kowack
Donate link: https://darx.net/projects/sape-api
Tags: sape, sape.ru, ad, ads, adsense, advert, advertising, links
Requires at least: 3.5
Tested up to: 4.4
Stable tag: 2.03
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate `Sape.ru` monetization to your site in two clicks.

== Description ==

**[ENG]** What implemented:

* No need download any files and archives or install anything manually, plugin will do everything automatically.
* Billing stats through API
* Perfect work with `wptexturize` filter
* All formats of links selling
* Contextual links selling
* Extended shortcode and widget support
* Articles selling in development
* Counter in footer to improve site load performance
* If you do not print all sold links on the page, remained links will be added into the footer of site in order to avoid appearance of links status `ERROR`
* Supporting translation

**[RUS]** Что реализовано:

* Нет необходимости скачивать файлы и архивы или устанавливать что-либо вручную. Плагин всё сделает автоматически.
* Статистика и прибыль по сайтам через API
* Плагин отлично работает с фильтром `wptexturize`
* Продажа всех форматов ссылок
* Контекстные ссылки (из текста статьи)
* Расширенная поддержка шорткодов и виджетов
* Продажа статей в разработке
* Счётчик в футере (подвале) сайта для поддержки производительности сайта
* Если вы выведите не все проданные ссылки на странице, то оставшиеся добавятся в футер (подвал) сайта во избежание появления у ссылок статуса `ERROR`
* Поддерживает перевод

== Installation ==

1. Upload `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

**[RUS]**

= Настройка плагина =

Нет необходимости скачивать файлы и архивы или устанавливать что-либо вручную.

Достаточно указать ваш идентификатор с системе `Sape`.

Плагин всё сделает автоматически.

= Статистика =

Возможность узнать статистику и прибыль по сайту из админ-панели Wordpress-а.

Достаточно указать логин и md5-хеш от пароля (для безопасности).

= wptexturize =

Плагин отлично дружит с фильтром `wptexturize`.

= Виджет =

Добавьте несколько копий виджета для размещения ссылок в разный местах.

= Шорткод =

Доступно следующее использование шорткода:

* **[sape]** — вывод всех ссылок в формате текста
* **[sape count=2]** — вывод лишь двух ссылок
* **[sape count=2 block=1]** — вывод ссылок в формате блока
* **[sape count=2 block=1 orientation=1]** — вывод ссылок в формате блока горизонтально
* **[sape]код другой биржи, html, js[/sape]** — вывод альтернативного текста при отсутствии ссылок.

Для вывода внутри темы (шаблона) используйте следующий код:

* `<?php echo do_shortcode('[sape]') ?>`

= Продажа статей =

В разработке.

= Расширение функционала =

Есть пожелания/идеи что добавить/изменить?
Пишите мне, с радостью отвечу.
Контакты внизу страницы на сайте
[darx.net](https://darx.net/#contacts)

= Плагин бесплатный ? =

Да, плагин абсолютно бесплатен, без какой либо рекламы. На веки вечные.

== Screenshots ==

1. Статистика через API.
2. Настройки доступа API.
3. Страница настроек плагина.
4. Пример виджета.

== Changelog ==

= 2.0 =
* Initial public release.

= 1.0 =
* Private beta testing.

== Upgrade Notice ==

= 2.0 =
* Initial public release.