import Sortable from "sortablejs";
import Notification from "@typo3/backend/notification.js";

// Configuration
const CONFIG = {
  animation: 150,
  ghostClass: "sortable-ghost",
  chosenClass: "sortable-chosen",
  dragClass: "sortable-drag",
  draggable: ".drag-item",
};

// Grid configurations
const GRID_CONFIGS = {
  "grid-1": { group: "grid1-grid2", handlers: ["onStart", "onEnd"], allowedTargets: ["grid-2", "grid-1"] },
  "grid-2": { group: ["grid1-grid2", "grid3-only"], handlers: ["onStart", "onEnd", "onAdd", "onRemove"], allowedTargets: ["grid-1", "grid-2"], put: true },
  "grid-3": { group: "grid3-only", handlers: ["onStart", "onEnd", "onAdd", "onRemove"], pull: "clone", allowedTargets: ["grid-2"], deletable: false },
  "grid-4": { group: "grid4-grid5", handlers: ["onStart", "onEnd", "onAdd"], allowedTargets: ["grid-5", "grid-4"] },
  "grid-5": { group: "grid4-grid5", handlers: ["onStart", "onEnd", "onAdd"], allowedTargets: ["grid-4", "grid-5"] },
};

// State management
class DragAndDropState {
  constructor() {
    this.enabledItems = [];
    this.disabledItems = [];
    this.itemPosition = [];
    this.itemPositionGrid5 = []; // New property for grid-5 positions
    this.sortableInstances = {};
    this.elements = {};
    this.debounceTimers = {}; // For debouncing AJAX requests
    this.initializeElements();
  }

  initializeElements() {
    // Grid elements
    Object.keys(GRID_CONFIGS).forEach(gridClass => {
      this.elements[gridClass] = document.querySelector(`.${gridClass}`);
    });

    // Form elements
    const selectors = {
      toggleButton: "#features-tab",
      toggleLabelButton: "#toggleLabel", 
      toggleLabel: ".toggle-label",
      presetSelector: "#rtePresets",
      enableFeatures: 'input[name="enable"]',
      disableFeatures: 'input[name="disabled"]',
      toolbarPosition: 'input[name="position"]',
      toolbarPositionGrid5: 'input[name="positionGrid5"]', // New field for grid-5 positions
      loaderDiv: "#rte-ckeditor__loader",
      searchInput: ".toolBarItems-search"
    };

    Object.entries(selectors).forEach(([key, selector]) => {
      this.elements[key] = key === 'toggleLabel' ? document.querySelectorAll(selector) : document.querySelector(selector);
    });
  }

  addItemToArray(array, dataId) {
    if (!array.includes(dataId)) array.push(dataId);
  }

  removeItemFromArray(array, dataId) {
    const index = array.indexOf(dataId);
    if (index > -1) array.splice(index, 1);
  }

  updateFeatureFields() {
    if (this.elements.enableFeatures) {
      this.elements.enableFeatures.value = this.enabledItems.join(",");
    }
    if (this.elements.disableFeatures) {
      this.elements.disableFeatures.value = this.disabledItems.join(",");
    }
  }

  updatePosition() {
    if (this.elements.toolbarPosition) this.elements.toolbarPosition.value = this.itemPosition.join(",");
  }

  updatePositionGrid5() {
    if (this.elements.toolbarPositionGrid5) this.elements.toolbarPositionGrid5.value = this.itemPositionGrid5.join(",");
  }

  updateGridPositions(gridType) {
    const grid = this.elements[gridType];
    if (!grid) return;
    
    const elements = grid.querySelectorAll("[data-items]");
    const positions = Array.from(elements, (element, key) => {
      element.setAttribute("data-index", key);
      return element.getAttribute("data-items").trim();
    });
    
    const cleanedPositions = this.cleanDuplicateSeparators(positions);
    
    if (gridType === "grid-2") {
      this.itemPosition = cleanedPositions;
      this.updatePosition();
    } else if (gridType === "grid-5") {
      this.itemPositionGrid5 = cleanedPositions;
      this.updatePositionGrid5();
    }
  }

  getGridClass(element) {
    return Array.from(element.classList).find(cls => cls.startsWith('grid-'));
  }

  isItemDeletable(item) {
    const grid = item.closest('.grid-1, .grid-2, .grid-3, .grid-4, .grid-5');
    if (!grid) return false;
    const gridClass = this.getGridClass(grid);
    return gridClass && GRID_CONFIGS[gridClass] && GRID_CONFIGS[gridClass].deletable !== false;
  }

  // Clean duplicate separators from position array - optimized version
  cleanDuplicateSeparators(positionArray) {
    if (!positionArray || positionArray.length === 0) return [];
    
    const cleaned = [];
    let lastItem = null;
    
    for (let i = 0; i < positionArray.length; i++) {
      const item = positionArray[i];
      
      // Skip consecutive separator duplicates
      if (item === lastItem && (item === '|' || item === '-')) {
        continue;
      }
      
      cleaned.push(item);
      lastItem = item;
    }
    
    return cleaned;
  }
}

// Event handlers
class EventHandlers {
  constructor(state) {
    this.state = state;
  }

  extractItemData(item) {
    if (!item) return null;
    
    const toggleBtn = item.querySelector(".toggle-btn");
    let dataId = toggleBtn?.getAttribute("data-id") || item.getAttribute("data-id");
    let dataItems = toggleBtn?.getAttribute("data-items") || item.getAttribute("data-items");
    
    // Generate default data for separator items - preserve original separator type
    if ((!dataId || !dataItems) && item.classList.contains('drag-separator-item')) {
      dataId = `separator_${Date.now()}`;
      // Get separator type from button's data-items attribute
      const button = item.querySelector('button[data-items]');
      dataItems = button?.getAttribute("data-items") || '-';
      item.setAttribute("data-id", dataId);
      item.setAttribute("data-items", dataItems);
    }
    
    return { dataId, dataItems, toggleBtn };
  }

  handleItemMove(evt, targetGrid) {
    const itemData = this.extractItemData(evt.item);
    if (!itemData?.dataId) return;

    const { dataId, dataItems, toggleBtn } = itemData;
    const sourceGrid = evt.from ? this.state.getGridClass(evt.from) : "";
    const operationType = sourceGrid === targetGrid ? "reorder" : "move";


    this.state.updateGridPositions(targetGrid);
    
    if (operationType === "move" && toggleBtn) {
      this.updateButtonClasses(toggleBtn, targetGrid, dataId);
      this.state.updateFeatureFields();
    }

    this.sendAjaxRequest({
      action: "handleAjaxRequest", 
      operationType, 
      itemId: dataId, 
      itemData: dataItems,
      sourceGrid, 
      targetGrid, 
      newIndex: evt.newIndex, 
      oldIndex: evt.oldIndex
    });
  }

  handleItemAdd(evt, targetGrid) {
    const itemData = this.extractItemData(evt.item);
    if (!itemData?.dataId || evt.from === evt.to) return;

    const { dataId, dataItems, toggleBtn } = itemData;
    const sourceGrid = evt.from ? this.state.getGridClass(evt.from) : "";
    const isClone = sourceGrid === "grid-3" && targetGrid === "grid-2";

    this.handleAddOperation(evt, dataId, dataItems, toggleBtn, sourceGrid, targetGrid, isClone);
  }

  // Consolidated add operation handler
  handleAddOperation(evt, dataId, dataItems, toggleBtn, sourceGrid, targetGrid, isClone = false) {
    if (isClone && evt.item) {
      evt.item.setAttribute("data-items", dataItems);
      evt.item.setAttribute("data-index", evt.newIndex);
      evt.item.setAttribute("data-cloned-from", "grid-3");
    }
    
    if (toggleBtn) this.updateButtonClasses(toggleBtn, targetGrid, dataId);
    this.state.updateFeatureFields();
    this.state.updateGridPositions(targetGrid);
    
    this.sendAjaxRequest({
      action: "handleAjaxRequest", 
      operationType: isClone ? "clone" : "add", 
      itemId: dataId, 
      itemData: dataItems,
      sourceGrid, 
      targetGrid, 
      newIndex: evt.newIndex, 
      oldIndex: evt.oldIndex, 
      isClone
    });
  }

  handleItemRemove(evt, sourceGrid) {
    const itemData = this.extractItemData(evt.item);
    if (!itemData?.dataId) return;

    const { dataId, dataItems } = itemData;

    // Update arrays based on source grid
    if (sourceGrid === "grid-2" || sourceGrid === "grid-5") {
      this.state.removeItemFromArray(this.state.enabledItems, dataId);
      this.state.addItemToArray(this.state.disabledItems, dataItems);
    } else if (sourceGrid === "grid-4") {
      this.state.removeItemFromArray(this.state.disabledItems, dataId);
    }
    
    this.state.updateFeatureFields();
    this.state.updateGridPositions(sourceGrid);

    this.sendAjaxRequest({
      action: "handleAjaxRequest", 
      operationType: "remove", 
      itemId: dataId, 
      itemData: dataItems,
      sourceGrid, 
      targetGrid: evt.to ? this.state.getGridClass(evt.to) : "",
      newIndex: evt.newIndex, 
      oldIndex: evt.oldIndex
    });
  }

  updateButtonClasses(toggleBtn, targetGrid, dataId) {
    if (!toggleBtn) return;
    
    const isEnabled = targetGrid === "grid-2" || targetGrid === "grid-5";
    
    // Always ensure btn-default is present
    toggleBtn.classList.add("btn-default");
    
    if (isEnabled) {
      // For grid-2 and grid-5: add active class
      toggleBtn.classList.add("btn-default--active");
      this.state.addItemToArray(this.state.enabledItems, dataId);
      this.state.removeItemFromArray(this.state.disabledItems, dataId);
    } else {
      // For grid-1 and others: remove active class
      toggleBtn.classList.remove("btn-default--active");
      this.state.addItemToArray(this.state.disabledItems, dataId);
      this.state.removeItemFromArray(this.state.enabledItems, dataId);
    }
  }

  sendAjaxRequest(data) {
    // Debounce AJAX requests to prevent rapid firing
    const debounceKey = `${data.operationType}_${data.targetGrid}`;
    
    if (this.state.debounceTimers[debounceKey]) {
      clearTimeout(this.state.debounceTimers[debounceKey]);
    }
    
    this.state.debounceTimers[debounceKey] = setTimeout(() => {
      this.performAjaxRequest(data);
    }, 300); // 300ms debounce delay
  }

  // Optimized method to clean position data
  cleanPositionData() {
    const { elements, cleanDuplicateSeparators } = this.state;
    
    // Clean grid-2 position data
    if (elements.toolbarPosition?.value) {
      const cleaned = cleanDuplicateSeparators(elements.toolbarPosition.value.split(','));
      elements.toolbarPosition.value = cleaned.join(',');
    }
    
    // Clean grid-5 position data
    if (elements.toolbarPositionGrid5?.value) {
      const cleaned = cleanDuplicateSeparators(elements.toolbarPositionGrid5.value.split(','));
      elements.toolbarPositionGrid5.value = cleaned.join(',');
    }
  }


  async performAjaxRequest(data) {
    try {
      if (this.state.elements.loaderDiv) this.state.elements.loaderDiv.classList.add("ns-show-overlay");

      this.state.updateFeatureFields();
      this.state.updatePosition();
      this.state.updatePositionGrid5();
      this.cleanPositionData();

      const form = document.getElementById("ckEditorModules");
      if (!form) throw new Error("Form not found");

      const formData = new FormData(form);
      if (this.state.elements.presetSelector?.value) {
        formData.set('activePreset', this.state.elements.presetSelector.value);
      }

      // Add operation data
      Object.entries({
        'operation[action]': data.action,
        'operation[type]': data.operationType,
        'operation[itemId]': data.itemId,
        'operation[itemData]': data.itemData,
        'operation[sourceGrid]': data.sourceGrid,
        'operation[targetGrid]': data.targetGrid,
        'operation[newIndex]': data.newIndex,
        'operation[oldIndex]': data.oldIndex,
        ...(data.isClone && { 'operation[isClone]': '1' })
      }).forEach(([key, value]) => formData.set(key, value));

      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

      
      // Show success notifications
      if (data.operationType === 'reorder') {
        showNotification('Position updated successfully', 'success');
      } else if (data.operationType === 'clone' && (data.itemData === '|' || data.itemData === '-')) {
        showNotification(`Separator '${data.itemData}' added successfully`, 'success');
      }

    } catch (error) {
      console.error('AJAX request failed:', error);
      showNotification('Failed to update position. Please try again.', 'error');
      this.state.updateGridPositions(data.targetGrid);
    } finally {
      this.state.elements.loaderDiv?.classList.remove("ns-show-overlay");
    }
  }
}

// Sortable manager
class SortableManager {
  constructor(state, eventHandlers) {
    this.state = state;
    this.eventHandlers = eventHandlers;
    this.initializeSortables();
  }

  initializeSortables() {
    Object.entries(GRID_CONFIGS).forEach(([gridClass, config]) => {
      const element = this.state.elements[gridClass];
      if (!element) return;

      const sortableConfig = {
        ...CONFIG,
        group: Array.isArray(config.group) 
          ? { name: config.group.join(','), pull: config.pull || true, put: config.put || true }
          : { name: config.group, pull: config.pull || true, put: config.put || true },
        onStart: (evt) => {},
        onMove: (evt) => this.validateMovement(evt),
        onEnd: (evt) => {
          if (evt.from === evt.to) this.eventHandlers.handleItemMove(evt, gridClass);
        },
      };

      if (config.handlers.includes("onAdd")) {
        sortableConfig.onAdd = (evt) => this.eventHandlers.handleItemAdd(evt, gridClass);
      }

      if (config.handlers.includes("onRemove")) {
        sortableConfig.onRemove = (evt) => this.eventHandlers.handleItemRemove(evt, gridClass);
      }

      this.state.sortableInstances[gridClass] = new Sortable(element, sortableConfig);
    });
  }

  validateMovement(evt) {
    const sourceGrid = this.state.getGridClass(evt.from);
    const targetGrid = this.state.getGridClass(evt.to);
    
    if (!sourceGrid || !targetGrid) return true;
    
    if (sourceGrid === 'grid-3' && targetGrid === 'grid-3') {
      return false;
    }
    
    if (sourceGrid === 'grid-2' && targetGrid === 'grid-1') {
      if (evt.dragged?.classList.contains('drag-separator-item')) {
        return false;
      }
    }
    
    // Allow multiple separators - users can add as many as needed
    // The real issue is duplicate entries in position array, not multiple separators
    
    const sourceConfig = GRID_CONFIGS[sourceGrid];
    if (sourceConfig?.allowedTargets && !sourceConfig.allowedTargets.includes(targetGrid)) {
      return false;
    }
    
    return true;
  }
}

// UI event handlers
class UIEventHandlers {
  constructor(state) {
    this.state = state;
    this.initializeEventListeners();
  }

  initializeEventListeners() {
    if (this.state.elements.toggleButton) {
      this.state.elements.toggleButton.addEventListener("click", () => window.dispatchEvent(new Event("resize")));
    }

    if (this.state.elements.toggleLabelButton && this.state.elements.toggleLabel.length) {
    
      const applyToggleState = () => {
          const isOpen = localStorage.getItem('toggleLabel') === 'open';
  
          this.state.elements.toggleLabel.forEach(el => {
              if (isOpen) {
                  el.classList.remove("label-show");
              } else {
                  el.classList.add("label-show");
              }
          });
      };
  
      // Apply initial state from localStorage
      applyToggleState();
  
      // On click toggle state
      this.state.elements.toggleLabelButton.addEventListener("click", () => {
          const isOpen = localStorage.getItem('toggleLabel') === 'open';
  
          // Save opposite value
          localStorage.setItem('toggleLabel', isOpen ? 'closed' : 'open');
  
          // Apply updated state
          applyToggleState();
  
          // Optional resize event
          setTimeout(() => window.dispatchEvent(new Event("resize")), 200);
      });
    }  

    if (this.state.elements.presetSelector) {
      const presetForm = document.getElementById("presetForm");
      this.state.elements.presetSelector.addEventListener("change", () => {
        if (this.state.elements.loaderDiv) this.state.elements.loaderDiv.classList.add("ns-show-overlay");
        if (presetForm) presetForm.submit();
      });
    }

    this.initializeSeparatorItemDeletion();
  }

  initializeSeparatorItemDeletion() {
    document.addEventListener('dblclick', (event) => {
      const target = event.target.closest('.drag-separator-item');
      if (!target) return;

      if (this.state.isItemDeletable(target)) {
        event.preventDefault();
        event.stopPropagation();
        this.deleteSeparatorItem(target);
      } else {
        this.showDeletionBlockedNotification();
      }
    });
  }

  deleteSeparatorItem(item) {
    item.style.backgroundColor = '#ffebee';
    item.style.border = '2px solid #f44336';
    this.removeTooltips(item);
    
    const grid = item.closest('.grid-1, .grid-2, .grid-3, .grid-4, .grid-5');
    if (!grid) return;

    const gridClass = this.state.getGridClass(grid);
    const toggleBtn = item.querySelector(".toggle-btn");
    const dataId = toggleBtn?.getAttribute("data-id") || item.getAttribute("data-id");
    const dataItems = toggleBtn?.getAttribute("data-items") || item.getAttribute("data-items");

    setTimeout(() => {
      item.remove();
      this.state.updateGridPositions(gridClass);
      this.state.updateFeatureFields();
      
      new EventHandlers(this.state).sendAjaxRequest({
        action: "handleAjaxRequest",
        operationType: "delete",
        itemId: dataId,
        itemData: dataItems,
        sourceGrid: gridClass,
        targetGrid: "",
        newIndex: -1,
        oldIndex: -1
      });

      showNotification('Separator item deleted successfully', 'success');
    }, 300);
  }

  removeTooltips(item) {
    const ariaDescribedby = item.querySelector('.toggle-btn')?.getAttribute('aria-describedby');   
    const tooltipElements = document.getElementById(ariaDescribedby);    
    if (tooltipElements) {
      tooltipElements.remove();
    }
  }

  showDeletionBlockedNotification() {
    showNotification('This item cannot be deleted', 'warning');
  }
}

// Search manager for filtering items across all grids
class SearchManager {
  constructor(state) {
    this.state = state;
    this.searchDebounceTimer = null;
    this.initializeSearch();
  }

  initializeSearch() {
    if (!this.state.elements.searchInput) return;

    this.state.elements.searchInput.addEventListener('input', (event) => {
      this.handleSearchInput(event.target.value);
    });

    // Handle clear button if exists
    this.state.elements.searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        this.state.elements.searchInput.value = '';
        this.handleSearchInput('');
      }
    });
  }

  handleSearchInput(searchTerm) {
    // Debounce search to avoid excessive filtering
    if (this.searchDebounceTimer) {
      clearTimeout(this.searchDebounceTimer);
    }

    this.searchDebounceTimer = setTimeout(() => {
      this.filterItems(searchTerm.trim());
    }, 100); // 300ms debounce delay
  }

  filterItems(searchTerm) {
    // Get all drag items across all grids
    const allItems = document.querySelectorAll('.drag-item');
    
    if (!searchTerm || searchTerm === '') {
      // Remove all highlights when search is empty
      allItems.forEach(item => this.removeHighlight(item));
      return;
    }

    const searchLower = searchTerm.toLowerCase();
    
    allItems.forEach(item => {
      const searchableText = this.getSearchableText(item);
      const matches = searchableText.toLowerCase().includes(searchLower);
      
      if (matches) {
        this.highlightItem(item);
      } else {
        this.removeHighlight(item);
      }
    });
  }

  getSearchableText(item) {
    // Get text from multiple sources for better search coverage
    const toggleBtn = item.querySelector('.toggle-btn');
    if (!toggleBtn) return '';

    // Priority 1: data-bs-title attribute (title)
    let searchText = toggleBtn.getAttribute('data-bs-title') || '';
    
    // Priority 2: toggle-label text content
    const toggleLabel = item.querySelector('.toggle-label');
    if (toggleLabel) {
      searchText += ' ' + toggleLabel.textContent.trim();
    }
    
    // Priority 3: data-items attribute (toolbar items)
    const dataItems = toggleBtn.getAttribute('data-items') || '';
    if (dataItems && dataItems !== '|' && dataItems !== '-') {
      searchText += ' ' + dataItems;
    }
    
    // Priority 4: button title attribute
    const titleAttr = toggleBtn.getAttribute('title') || '';
    if (titleAttr) {
      searchText += ' ' + titleAttr;
    }

    return searchText.trim();
  }

  highlightItem(item) {
    // Add highlight class to matching items
    item.classList.add('search-highlight');
    const toggleBtn = item.querySelector('.toggle-btn');
    if (toggleBtn) {
      toggleBtn.classList.add('search-highlight-btn');
    }
  }

  removeHighlight(item) {
    // Remove highlight class from items
    item.classList.remove('search-highlight');
    const toggleBtn = item.querySelector('.toggle-btn');
    if (toggleBtn) {
      toggleBtn.classList.remove('search-highlight-btn');
    }
  }

  clearSearch() {
    if (this.state.elements.searchInput) {
      this.state.elements.searchInput.value = '';
      this.filterItems('');
    }
  }
}

// Utility functions
function showNotification(message, type = "info") {
  const notificationType = type === "error" ? "error" :
                           type === "success" ? "success" :
                           type === "warning" ? "warning" : "info";
  Notification[notificationType](message);
}

function initDragAndDropApp() {
  const state = new DragAndDropState();
  const eventHandlers = new EventHandlers(state);
  const sortableManager = new SortableManager(state, eventHandlers);
  const uiEventHandlers = new UIEventHandlers(state);
  const searchManager = new SearchManager(state);

  window.DragAndDropApp = {
    state,
    eventHandlers,
    sortableManager,
    uiEventHandlers,
    searchManager,
    showNotification,
    deleteSeparatorItem: (item) => uiEventHandlers.deleteSeparatorItem(item)
  };
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initDragAndDropApp);
} else {
  initDragAndDropApp();
}
