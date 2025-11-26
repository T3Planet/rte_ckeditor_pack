.. include:: ../../Includes.txt

.. _aichat:

==================
AI Chat
==================

The AI Chat feature provides a conversational AI assistant that supports content creation, editing, and ideation directly inside CKEditor. It enables dynamic, multi-turn interactions through a chat interface, offering a collaborative and context-aware writing experience beyond single-prompt generation.

.. rst-class:: horizbuttons-attention-m
    - `View Interactive Guide <https://app.supademo.com/demo/cmiel9hefb1seb7b4cxqxc0o4?step=2>`_ 

.. contents::
   :local:
   :depth: 2

Configuration
-------------

Adjust the AI configuration as needed. To enable the Chat feature, load the ``AIChat`` plugin in your CKEditor configuration. When enabled, a Chat button appears in the AI interface together with access to chat history.

1. Working with the Document
----------------------------

CKEditor AI Chat operates in the context of the active document. Reference specific paragraphs, request full-document proofreading, or ask questions about the currently visible content. Optional Web search and Reasoning features extend the assistant with real-time information and advanced logical processing.

2. Making Changes to Content
----------------------------

Request summarization, rewriting, or structural improvements. Instead of dumping plain text, AI Chat returns proposed edits that you can review, accept, reject, or convert into Track Changes suggestions—eliminating copy/paste workflows.

3. Brainstorming and Content Creation
-------------------------------------

Start from a blank page, generate ideas, build outlines, and refine drafts entirely through conversation. The AI can rewrite, proofread, or polish text whenever needed.

4. Integration
--------------

Enable the Chat feature by loading the ``AIChat`` plugin. Once active, the Chat button appears in the AI panel along with chat history controls.

5. Available Models
-------------------

Users pick from available AI models via the selector at the bottom of the chat panel. The chosen model remains active for the conversation; start a new chat (+ New chat) to switch models.

6. Web Search
-------------

Web search lets AI retrieve real-time facts, verify information, and generate up-to-date responses. Activate it with the **Enable web search** toggle for compatible models.

7. Reasoning
------------

Reasoning enhances problem solving, contextual analysis, and structured outputs. Turn it on with **Enable reasoning** when the model supports it.

8. Adding Context to Conversations
----------------------------------

Use **Add context** to attach URLs, files, or documents. The AI analyzes the supplied material to provide summaries, explanations, or answers. Integrations with centralized resource libraries are supported.

9. Working with AI-Generated Changes
------------------------------------

AI responses include proposed edits. Hovering a suggestion highlights the corresponding document section for context before applying it.

10. Showing Details
-------------------

Toggle **Show details** to switch between:
- Detailed view – shows markup for additions, deletions, and formatting.
- Simplified view – clean preview of the updated text.

11. Previewing Changes
----------------------

**Show in the text** opens a preview window with navigation, apply/reject controls, and Track Changes conversion options. The preview stays synced with the document.

12. Applying Changes
--------------------

- **Apply** inserts the selected suggestion.
- **Apply all** accepts every AI-generated change at once.

13. Inserting Track Changes Suggestions
---------------------------------------

With Track Changes enabled:
- **Insert suggestion** applies individual edits as suggestions.
- **Suggest** (under Apply all) converts all edits into Track Changes entries.

14. Rejecting Suggestions
-------------------------

Use **Delete (Reject)** to dismiss unwanted proposals.

15. Chat History
----------------

All conversations appear in the Chat history panel. Users can reopen sessions, rename conversations, delete entries, and filter by date or search term.

