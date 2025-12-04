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

