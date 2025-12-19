.. include:: ../Includes.txt

.. _toolbarandpresets:

==================
Toolbar & Presets
==================

.. figure:: images/Preset.png
   :alt: Basic Configuration

You can easily create your own RTE presets or edit your preset using the drag-and-drop UI toolbar management. Take a look at the interactive demo below::

.. rst-class:: horizbuttons-attention-m

   - `View Interactive Guide <https://app.supademo.com/demo/cmir7k9mw0rsjl82161ungk5i?preview=true&step=2>`_


Advanced Features for Managing RTE Presets
===========================================

We have added new preset management features in CKEditor to support both editors and developers, making configuration handling faster, safer, and more consistent.

1. Load from YAML
---------------------

The Load from YAML feature retrieves the default RTE configuration directly from the TYPO3 core YAML file. It allows integrators to access or restore the original settings quickly, without manually navigating system directories.

.. rst-class:: horizbuttons-attention-m

   - `View Interactive Guide <https://app.supademo.com/demo/cmirfho5i150pl821fkxxm2ji?step=2>`_

2. Reset
-----------------

The Reset feature restores all modified configuration settings to their default state. This provides a clean baseline and helps prevent issues caused by incorrect or experimental changes.

.. rst-class:: horizbuttons-attention-m

   - `View Interactive Guide <https://app.supademo.com/demo/cmiresoe813yql821w5tt6n1r?step=3>`_

3. Sync
-----------------

The Sync feature aligns configuration values between the default TYPO3 RTE YAML file and the extension’s custom YAML file. It ensures both remain consistent, reduces conflicts, and keeps behavior uniform across environments.

.. rst-class:: horizbuttons-attention-m

   - `View Interactive Guide <https://app.supademo.com/demo/cmirg3t7q15sll821k3ahlmmx?step=2>`_

4. Import / Export Presets
-------------------------

The Import / Export Presets feature allows you to easily manage and share CKEditor presets between different TYPO3 environments. It helps you keep the same editor configuration across local, staging, and live systems without manual setup.

Import Presets
^^^^^^^^^^^^^^

The Import Presets option lets you add CKEditor presets from a YAML file into your TYPO3 system.  
This is useful when you receive a preset from another environment or project and want to reuse the same editor setup.

Simply upload the YAML file, and the preset will be created automatically with all toolbar buttons, groups, and settings.

.. rst-class:: horizbuttons-attention-m

   - `View Interactive Guide <https://app.supademo.com/embed/cmjcvzsq24n7lf6zp2wlvrmgm?embed_v=2&utm_source=embed>`_

Export Presets
^^^^^^^^^^^^^^

The Export Presets option allows you to download existing or custom CKEditor presets as a YAML file.  
This makes it easy to share presets with other TYPO3 systems or team members.

You can select a preset and export it, then import the same file into another TYPO3 installation to get the exact same configuration.

.. rst-class:: horizbuttons-attention-m

   - `View Interactive Guide <https://app.supademo.com/embed/cmjcvdeyg4mjzf6zpd00w121z?embed_v=2&utm_source=embed>`_

.. note::

   When exporting presets, the following features are **not included**
   in the generated YAML file:

   - Document Outline
   - Paste Office Enhanced
   - Editoria11y
   - Markdown