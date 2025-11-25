/**
 * @file This is what CKEditor refers to as a master (glue) plugin. Its role is
 * just to load the “editing” and “UI” components of this Plugin. Those
 * components could be included in this file, but
 *
 * I.e, this file's purpose is to integrate all the separate parts of the plugin
 * before it's made discoverable via index.js.
 */
// cSpell:ignore Emoji Emoji

// The contents of Emoji and Emoji editing could be included in this
// file, but it is recommended to separate these concerns in different files.

// CkEditor
import { Plugin } from '@ckeditor/ckeditor5-core';
import { Typing } from '@ckeditor/ckeditor5-typing';
import { createDropdown } from '@ckeditor/ckeditor5-ui';
import { CKEditorError } from '@ckeditor/ckeditor5-utils';

// UI
import EmojiCharactersNavigationView from '@t3planet/RteCkeditorPack/emoji-characters-navigation-view';
import CharacterGridView from '@t3planet/RteCkeditorPack/character-grid-view';
import CharacterInfoView from '@t3planet/RteCkeditorPack/character-info-view';
import CharacterSearchView from '@t3planet/RteCkeditorPack/character-search-view';

// Emoji
import EmojiActivity from '@t3planet/RteCkeditorPack/emoji-activity';
import EmojiFlags from '@t3planet/RteCkeditorPack/emoji-flags';
import EmojiFood from '@t3planet/RteCkeditorPack/emoji-food';
import EmojiNature from '@t3planet/RteCkeditorPack/emoji-nature';
import EmojiObjects from '@t3planet/RteCkeditorPack/emoji-objects';
import EmojiPeople from '@t3planet/RteCkeditorPack/emoji-people';
import EmojiPlaces from '@t3planet/RteCkeditorPack/emoji-places';
import EmojiSymbols from '@t3planet/RteCkeditorPack/emoji-symbols';


const emojIIcon = '<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M437.02 74.98C388.667 26.629 324.38 0 256 0S123.333 26.629 74.98 74.98C26.629 123.333 0 187.62 0 256s26.629 132.668 74.98 181.02C123.333 485.371 187.62 512 256 512s132.667-26.629 181.02-74.98C485.371 388.668 512 324.38 512 256s-26.629-132.667-74.98-181.02zM256 472c-119.103 0-216-96.897-216-216S136.897 40 256 40s216 96.897 216 216-96.897 216-216 216z"/><path d="M368.993 285.776c-.072.214-7.298 21.626-25.02 42.393C321.419 354.599 292.628 368 258.4 368c-34.475 0-64.195-13.561-88.333-40.303-18.92-20.962-27.272-42.54-27.33-42.691l-37.475 13.99c.42 1.122 10.533 27.792 34.013 54.273C171.022 389.074 212.215 408 258.4 408c46.412 0 86.904-19.076 117.099-55.166 22.318-26.675 31.165-53.55 31.531-54.681l-38.037-12.377z"/><circle cx="168" cy="180.12" r="32"/><circle cx="344" cy="180.12" r="32"/></svg>';
const ALL_EMOJI_CHARACTERS_GROUP = 'All';

export default class Emoji extends Plugin {

  /**
 * @inheritDoc
 */
  static get requires() {
    return [Typing];
  }

  /**
    * @inheritDoc
  */
  static get pluginName() {
    return 'Emoji';
  }

  /**
   * @inheritDoc
   */
  constructor(editor) {
    super(editor);
    this._characters = new Map();
    this._groups = new Map();

    const emojis = this.getEmojis();

    emojis.forEach(emoji => {
      this.addItems(emoji.prototype.getEmoji()[0], emoji.prototype.getEmoji()[1]);
    });
  }

  /**
   * @inheritDoc
   */
  init() {
    const editor = this.editor;
    const t = editor.t;

    const inputCommand = editor.commands.get('input');

    // This will register the Emoji toolbar button.
    editor.ui.componentFactory.add('Emoji', (locale) => {
      const inputCommand = editor.commands.get('input');
      const dropdownView = new createDropdown(locale);
        let dropdownPanelContent;


      // Create the toolbar button.
      dropdownView.buttonView.set({
        label: editor.t('Emoji'),
        icon: emojIIcon,
        tooltip: true,
      });

      // Bind the state of the button to the command.
        dropdownView.bind('isEnabled').to(inputCommand);

      // Execute the command when the button is clicked (executed).

      // Insert a special character when a tile was clicked.
      dropdownView.on('execute', (evt, data) => {
        editor.execute('input', { text: data.character });
        editor.editing.view.focus();
      });

      dropdownView.on('change:isOpen', () => {
          if (!dropdownPanelContent) {
            dropdownPanelContent = this._createDropdownPanelContent(locale, dropdownView);

            dropdownView.panelView.children.add(dropdownPanelContent.navigationView);
            dropdownView.panelView.children.add(dropdownPanelContent.searchView);
            dropdownView.panelView.children.add(dropdownPanelContent.gridView);
            dropdownView.panelView.children.add(dropdownPanelContent.infoView);
          }

          dropdownPanelContent.infoView.set({
            character: null,
            name: null
          });
        });

        return dropdownView;
    });
  }

  getEmojis() {
    return [
      EmojiPeople,
      EmojiFood,
      EmojiNature,
      EmojiActivity,
      EmojiSymbols,
      EmojiPlaces,
      EmojiObjects,
      EmojiFlags
    ]
  }

  addItems(groupName, items) {
    if (groupName === ALL_EMOJI_CHARACTERS_GROUP) {
      throw new CKEditorError(
        `emoji-group-error-name: The name "${ALL_EMOJI_CHARACTERS_GROUP}" is reserved and cannot be used.`
      );
    }

    const group = this._getGroup(groupName);
    for (const item of items) {
      group.add(item.title);
      this._characters.set(item.title, item.character);
    }
  }

  getGroups() {
    return this._groups.keys();
  }

  getCharactersForGroup(groupName, characterContainString="") {

    if (groupName === ALL_EMOJI_CHARACTERS_GROUP) {
    let characters = new Set(this._characters.keys());
      if (characterContainString) {
        characters.forEach((character, index) => {
          if (!this.getCharacterContainedSearchedString(character, characterContainString)) {
            characters.delete(character);
          }
        });
      }
      return characters;
    }
    return this._groups.get(groupName);
  }

  getCharacterContainedSearchedString(character, searchString) {
    const lowerMainString = character.toLowerCase();
    const lowerSubString = searchString.toLowerCase();
    return lowerMainString.includes(lowerSubString);
  }

  getCharacter(title) {
    return this._characters.get(title);
  }

  _getGroup(groupName) {
    if (!this._groups.has(groupName)) {
      this._groups.set(groupName, new Set());
    }
    return this._groups.get(groupName);
  }

  _updateGrid(currentGroupName, gridView,characterContainString = '') {
    gridView.tiles.clear();
    if (characterContainString) {
      currentGroupName = 'All';
    }
    const characterTitles = this.getCharactersForGroup(currentGroupName,characterContainString);
    if (characterTitles) {
      for (const title of characterTitles) {
        const character = this.getCharacter(title);
        gridView.tiles.add(gridView.createTile(character, title));
      }
    }
  }

  debounce(callback, wait) {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(function () { callback.apply(this, args); }, wait);
    };
  }

  _createDropdownPanelContent(locale, dropdownView) {
    const specialCharsGroups = [...this.getGroups()];

    // Add a special group that shows all available special characters.
    specialCharsGroups.unshift(ALL_EMOJI_CHARACTERS_GROUP);

    const navigationView = new EmojiCharactersNavigationView(locale, specialCharsGroups);
    const gridView = new CharacterGridView(locale);
    const searchView = new CharacterSearchView(locale,specialCharsGroups);
    const infoView = new CharacterInfoView(locale);

    gridView.delegate('execute').to(dropdownView);
    gridView.delegate('execute').to(searchView);

    gridView.on('tileHover', (evt, data) => {
      infoView.set(data);
    });

    // return search results.
    searchView.on('keyup', this.debounce((searchObj) => {
      if (searchObj.source.element.value) {
        this._updateGrid('All', gridView, searchObj.source.element.value);
      }else {
        this._updateGrid('All', gridView);
      }
    }, 500));

    // Update the grid of special characters when a user changed the character group.
    navigationView.on('execute', () => {
      this._updateGrid(navigationView.currentGroupName, gridView);
    });

    // Set the initial content of the special characters grid.
    this._updateGrid(navigationView.currentGroupName, gridView);

    return { searchView, navigationView, gridView, infoView };
  }

}
