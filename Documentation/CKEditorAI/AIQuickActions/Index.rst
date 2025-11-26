.. include:: ../../Includes.txt

.. _aiquickactions:

==================
AI Quick Actions
==================

AI Quick Actions streamline routine transformations by surfacing one-click, AI-powered suggestions within CKEditor. Users can enhance, refine, or analyze selected text instantly, or send it to Chat for deeper exploration.

.. rst-class:: horizbuttons-attention-m
    - `View Interactive Guide <https://app.supademo.com/demo/cmiel9hefb1seb7b4cxqxc0o4?step=11>`_ 

Integration
-----------

1. Load the ``AIQuickActions`` plugin in your CKEditor configuration.
2. Add the Quick actions menu (``aiQuickActions``) to either the main toolbar or the balloon toolbar. Follow the toolbar configuration guide for placement details.
3. Optionally expose individual shortcuts (e.g., ``ask-ai``, ``improve-writing``) or entire categories such as **Adjust length** or **Change tone** for faster access.

Types of Actions
----------------

**Actions that open Chat**

These push the selection into the Chat panel, optionally with a pre-filled prompt.

Examples:
- ``ask-ai`` – Opens Chat with the selected text as context.
- ``summarize`` – Opens Chat and auto-generates a summary request.

**Actions that open a popup**

These display an AI proposal in a contextual popup with Accept, Reject, and Re-run options.

Examples:
- ``continue``
- ``make-shorter``

Default Quick Actions
---------------------

Built-in actions include (grouped by category):

- ``ask-ai``
- ``chat-commands``
- ``explain``
- ``summarize``
- ``highlight-key-points``
- ``improve-writing``
- ``continue``
- ``fix-grammar``

**Adjust length**  
``make-shorter``, ``make-longer``

**Change tone**  
``make-tone-casual``, ``make-tone-direct``, ``make-tone-friendly``, ``make-tone-confident``, ``make-tone-professional``

**Translate**  
``translate-to-english``, ``translate-to-chinese``, ``translate-to-french``, ``translate-to-german``, ``translate-to-italian``, ``translate-to-portuguese``, ``translate-to-russian``

Customization
-------------

- Add custom actions tailored to your workflows.
- Remove unused defaults to declutter the menu.
- Reorder or re-categorize actions to match project needs.
- Place high-usage shortcuts directly on the toolbar or balloon UI.

Each Quick Action can also serve as a conversation starter with AI Chat, ensuring consistent handoff between quick transforms and deeper ideation.

