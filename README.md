OmegaWiki
===============

OmegaWiki works as a MediaWiki extension to allow for storing multilingual linguistic data in a structured database.

Read more at www.omegawiki.org

Besides these it also contains some tools and applications and an API.

Subdirectories
--------------
- Database scripts/
	- /Convenience - useful scripts for developers
	- /Incremental - necessary update scripts to keep your extension installation in sync with the trunk
- Images/ - images used in the Wikidata UI
- OmegaWiki/ - the current main (only) application of the Wikidata framework
- includes/ - contains WikiLexicalData's WikiMedia extensions of Special Pages, Tags and API
- maintenance/ - currently contains our own update.php
- perl-tools/ - import/export tools written in Perl ( outdated )
- php-tools/ - import/export tools written in PHP ( outdated )
- util/ - ( outdated )


Updating the database
---------------------
Go to the maintenance folder of the OmegaWiki extension.

run: php update.php

This will install the base schema, if it wasn't installed yet. Call
MediaWiki's update.php, which will update both MediaWiki and OmegaWiki updates,
then give instruction on globals one needs to add so that the OmegaWiki
software runs smoothly (again, if freshly installed ).
