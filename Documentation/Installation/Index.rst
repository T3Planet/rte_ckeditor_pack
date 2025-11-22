.. include:: ../Includes.txt

.. _installation:

===========================
Installation
===========================

Requirements
============

* TYPO3 v12 or v13 with composer mode enabled.
* PHP 8.1+ (matches the TYPO3 core matrix).
* Access to the extension root ``packages/rte_ckeditor_pack`` and the
  ability to run composer commands.

Composer Setup
==============

1. Require the package in your project root:

   .. code-block:: bash

      composer config repositories.rte_ckeditor_pack path packages/rte_ckeditor_pack
      composer require |package_name|:dev-main

   Adjust the version constraint to the tagged release you want to pin.

2. Flush caches after installation:

   .. code-block:: bash

      ./vendor/bin/typo3 cache:flush

Activate the Extension
======================

* Log in to the TYPO3 backend and navigate to **Extensions**.
* Search for *Richtext Collaborators* and activate it if it is not auto-enabled by
  composer mode.
* Confirm that the dashboard module becomes available under **RTE CKEditor**.

Include TypoScript
==================

The extension ships TypoScript setup that registers the RTE presets and
backend modules. Include it globally via the site package:

.. code-block:: typoscript

   @import 'EXT:|extension_key|/Configuration/TypoScript/setup.typoscript'

Database Schema
===============

Apply the Database Compare (``Install Tool`` or TYPO3 CLI) to create the
domain models that store toolbar groups, configuration items, and revision
metadata:

.. code-block:: bash

   ./vendor/bin/typo3 database:updateschema

Post-Install Checklist
======================

* Verify backend modules render without errors.
* Ensure CKEditor instances pick up the packaged presets.
* Check browser console to confirm JavaScript modules from
  ``EXT:|extension_key|/Resources/Public/JavaScript`` load correctly.

