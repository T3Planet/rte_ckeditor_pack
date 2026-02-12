.. include:: ../Includes.txt

.. _generalhtmlsupport:

General HTML Support – TYPO3 Feature
====================================

Overview
--------

General HTML Support is a feature of :entity:organization:TYPO3 CMS that works together with existing Processing YAML styling and the RTE configuration.

It allows developers and editors to keep using their existing custom HTML structure along with ``RTE_ckeditor_pack`` HTML settings, without needing extra custom development.

Purpose
-------

This feature helps you to:

- Use your existing project-specific HTML

- Combine Processing YAML rules with the RTE setup

- Keep approved HTML tags, classes, and attributes

- Make the editor more flexible in a controlled way

How It Works
------------
By default, activating HTML support does not automatically permit any additional markup. Instead, all allowed elements must be clearly defined in the General HTML Support configuration.

This includes defining:

- permitted HTML elements (for example: ``section``, ``div``, ``article``, ``span``, ``iframe``)

- allowed attributes (such as ``id``, ``data-*``, ``class``, ``style``)

- approved CSS classes and styles

After configuration:

- The editor accepts the defined HTML

- Allowed HTML is kept while editing

- Content is saved without removing approved markup

- Any HTML that is not allowed will be automatically removed

Important Note
--------------

This feature only keeps the HTML that is clearly allowed in the configuration.
- Allowed HTML elements and attributes will stay in the content
- Not allowed or undefined HTML will be removed automatically

For example:

- If ``div`` is allowed, it will remain in the content

- If ``div`` is not allowed, it will be removed when the content is processed

.. rst-class:: horizbuttons-attention-m

   - `View Interactive Guide <https://app.supademo.com/embed/cmljcxvlv45r75351wy2g70sh>`_
