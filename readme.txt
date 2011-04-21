=== ManageWP Worker ===
Contributors: freediver
Donate link: https://www.networkforgood.org/donation/MakeDonation.aspx?ORGID2=520781390
Tags: managewp, managewp worker, admin, manage blogs, multi blog manager, manage multiple blogs, remote blog management
Requires at least: 3.0
Tested up to: 3.1.1
Stable tag: trunk

ManageWP Worker plugin allows you to remotely manage your blogs from one dashboard.

== Description ==

ManageWP is a revolutionary plugin that allows you to manage multiple WordPress blogs from one dashboard.

Main features:

*   Secure! No passwords for sites required, uses OpenSSL encrypted protocol
*   One click upgrades of WordPress, plugin and themes across all your sites
*   One click to access administration dashboard for any site
*   Bulk publish posts to multiple sites at once
*   Bulk upload themes and plugins to multiple sites at once
*   Automatic backups of your sites
*   Clone one site to another
*   Much, much more...

Check out [ManageWP.com](http://managewp.com/ "Manage Multiple Blogs")

API for developers available at [ManageWP.com/API](http://managewp.com/api "ManageWP API")

== Changelog ==  

= 3.8.8 =
* New feature: Bulk add links to blogroll
* New feature: Manual backups to email address
* New feature: Backup requirements check (under ‘Manage Backups’)
* New feature: Popup menu for groups allowing to show dashboard for that group only
* New feature: Favorite list for plugins and themes for later quick installation to multiple blogs
* New feature: Invite friends
* Fixed: problem with backups and write permissions when upload dir was wrongly set
* Fixed: problem adding sites where WordPress is installed in a folder
* Fixed: 408 error message problem when adding site
* Fixed: site time out problems when adding site
* Fixed: problems with some WP plugins (WP Sentinel)
* Fixed: problems with upgrade notifications

= 3.8.7 =
* Fixed 408 error when adding sites
* Added support for IDN domains
* Fixed bug with WordPress updates
* Added comment moderation to the dashboard
* Added quick links for sites (menu appears on hover)


= 3.8.6 =
* Added seach websites feature
* Enhanced dashboard actions (spam comments, post revisions, table overhead)
* Added developer [API] (http://managewp.com/api "ManageWP API")
* Improved Migrate/Clone site feature

= 3.8.4 =
* Fixed remote dashboard problems for sites with redirects
* Fixed IE7 issues in the dashboard

= 3.8.3 =
* Fixed problem with capabilities

= 3.8.2 =
* New interface
* SSL security protocol
* No passwords required
* Improved clone/backup


= 3.6.3 =  
* Initial public release

== Installation ==

1. Upload the plugin folder to your /wp-content/plugins/ folder
2. Go to the Plugins page and activate ManageWP Worker
3. Visit [ManageWP.com](http://managewp.com/ "Manage Multiple Blogs"), sign up and add your site

Alternately

1. Visit [ManageWP.com](http://managewp.com/ "Manage Multiple Blogs"), sign up and add your site
2. ManageWP will warn you the worker plugin is not installed and offer a link for quick installation

== Screenshots ==

1. ManageWP dashboard with available upgrades, site statistics and management functions



== License ==

This file is part of ManageWP Worker.

ManageWP Worker is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

ManageWP Worker is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with ManageWP Worker. If not, see <http://www.gnu.org/licenses/>.


== Frequently Asked Questions ==

= I have problems adding my site =

Make sure you use the latest version of the worker plugin on the site you are trying to add. If you do, sometimes deactivating and activating it again will help. If you still have problems, [contact us](http://managewp.com/contact "ManageWP Contact").

= I have problems installing new plugins or upgrading WordPress through ManageWP =

ManageWP Worker relies on properly set file permissions on your server. See the [user guide](http://managewp.com/user-guide#ftp "ManageWP user guide") for more tips.