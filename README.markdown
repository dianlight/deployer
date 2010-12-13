# Deployer #

** ATTENTION ** 
This is a early alpha version and is not tested in all condition and environments. 
Use with CAUTION only as Technology Preview not on production environment. 
** ATTENTION ** 

This externsion allow to manage two environment at time.
One environment is the stage and is a "standard" Symphony.CMS installation where authors can develop the website.
The other environment is the production where a snopshot of the stage enviroment live without in a "readonly" mode.

- Version: 0.02
- Date: 13th Dec 2010
- Requirements: Symphony 2.1.0 or above, ZIP enabled (--enable-zip)
- Author: Lucio Tarantino, lucio.tarantino@gmail.com
- Constributors: [A list of contributors can be found in the commit history]
- GitHub Repository: <http://github.com/dianlight/deployer>


## Background

In my company we need to manage continus develop of website in production but on a single SymphonyCMS installation is very difficult.
A signle developer or author (in my company are copywriters and marketing peoples without technical expertice ) can insert bug or error ant drop the site avability.
So I create a two enviroment architecture, one for staging and develop and one for production. When a new version of a site is ready and tested in stage environment I create the Ensemble (whith "Export Ensamble" extension) and move all to the production environment. 
This extensions is a try to autmate the procedure.

This is my first extension for Symphony and I'm not expert in PHP (I'm a Java developer) so I borrow function and code from the original Export Ensamble and FileManager extensions.


## Synopsis

This extension is based on "Export Ensamble" and "FileManager" and will create and manage an installable version of your Symphony install useful for automatic deploy. 
With a simple interface you can create "Snapshoot" of the current environment.
The resultant archive contains install.php, install.sql and workspace/install.sql files so can be used to create a new Symphony release but also contains information to allow deploy on an existing configured symphony intallation.
The ZIP module of PHP is utilised, and is memory efficient, allowing for larger sites to be exported without the need for increasing PHP's memory limit.

Currently this extension adds a  "System->Deployer" menu and some configuration fields on the preferences page. 

The Deploy procedure is a simple unzip of the Ensamble in the target directory and the execution of the two .sql scripts on the target DB. 
This deploy method is what I call "Hard Deploy" beacuse all tables on the target DB are dropped and recreate.
In the future it will insert waht I call "Soft Deploy" to allow a sort of DB sync between the Ensamble and the running DB so no downtime is perfromed on the running website.


## Installation & Updating

Information about [installing and updating extensions](http://symphony-cms.com/learn/tasks/view/install-an-extension/) can be found in the Symphony documentation at <http://symphony-cms.com/learn/>.


## Change Log

**Version 0.2** ( 13th Dec 2010 Alpha2)

- BUG #1: "Deployed Date" is the date of ensemble not the date of the deploy.
- .htaccess is moved in root only on configured ensemble. (BUG #2: Add check to remove .htaccess on first installation)


**Version 0.1** ( 12th Dec 2010 Alpha) 

- Initial version.

## Roadmap / TODO

- Implement Soft Deploy mode when non schema changes are made on DB.
- Add MD5 CRC and ZIP test on Ensamble to prevent "Invalid ZIP" error on deploy.
- Add User Comments on SnapShoots
- Add Site Recovery on a failed Deploy.
- Add aumatic maintenance_mode on deploy if needed (Hard Deploy?)
- UI Improvement ( Progress Bars?)
- Add option to include/exclude .htaccess on deploy.
- Add option to include authors.
- Add option to create a new Admin on deploy.
- Write a Manual