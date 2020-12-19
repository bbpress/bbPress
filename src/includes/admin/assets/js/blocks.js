// modules are defined as an array
// [ module function, map of requires ]
//
// map of requires is short require name -> numeric require
//
// anything defined in a previous bundle is accessed via the
// orig method which is the require for previous bundles
parcelRequire = (function (modules, cache, entry, globalName) {
  // Save the require from previous bundle to this closure if any
  var previousRequire = typeof parcelRequire === 'function' && parcelRequire;
  var nodeRequire = typeof require === 'function' && require;

  function newRequire(name, jumped) {
    if (!cache[name]) {
      if (!modules[name]) {
        // if we cannot find the module within our internal map or
        // cache jump to the current global require ie. the last bundle
        // that was added to the page.
        var currentRequire = typeof parcelRequire === 'function' && parcelRequire;
        if (!jumped && currentRequire) {
          return currentRequire(name, true);
        }

        // If there are other bundles on this page the require from the
        // previous one is saved to 'previousRequire'. Repeat this as
        // many times as there are bundles until the module is found or
        // we exhaust the require chain.
        if (previousRequire) {
          return previousRequire(name, true);
        }

        // Try the node require function if it exists.
        if (nodeRequire && typeof name === 'string') {
          return nodeRequire(name);
        }

        var err = new Error('Cannot find module \'' + name + '\'');
        err.code = 'MODULE_NOT_FOUND';
        throw err;
      }

      localRequire.resolve = resolve;
      localRequire.cache = {};

      var module = cache[name] = new newRequire.Module(name);

      modules[name][0].call(module.exports, localRequire, module, module.exports, this);
    }

    return cache[name].exports;

    function localRequire(x){
      return newRequire(localRequire.resolve(x));
    }

    function resolve(x){
      return modules[name][1][x] || x;
    }
  }

  function Module(moduleName) {
    this.id = moduleName;
    this.bundle = newRequire;
    this.exports = {};
  }

  newRequire.isParcelRequire = true;
  newRequire.Module = Module;
  newRequire.modules = modules;
  newRequire.cache = cache;
  newRequire.parent = previousRequire;
  newRequire.register = function (id, exports) {
    modules[id] = [function (require, module) {
      module.exports = exports;
    }, {}];
  };

  var error;
  for (var i = 0; i < entry.length; i++) {
    try {
      newRequire(entry[i]);
    } catch (e) {
      // Save first error but execute all entries
      if (!error) {
        error = e;
      }
    }
  }

  if (entry.length) {
    // Expose entry point to Node, AMD or browser globals
    // Based on https://github.com/ForbesLindesay/umd/blob/master/template.js
    var mainExports = newRequire(entry[entry.length - 1]);

    // CommonJS
    if (typeof exports === "object" && typeof module !== "undefined") {
      module.exports = mainExports;

    // RequireJS
    } else if (typeof define === "function" && define.amd) {
     define(function () {
       return mainExports;
     });

    // <script>
    } else if (globalName) {
      this[globalName] = mainExports;
    }
  }

  // Override the current require with this new one
  parcelRequire = newRequire;

  if (error) {
    // throw error from earlier, _after updating parcelRequire_
    throw error;
  }

  return newRequire;
})({"components/forumPicker.jsx":[function(require,module,exports) {
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }

function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _iterableToArrayLimit(arr, i) { if (typeof Symbol === "undefined" || !(Symbol.iterator in Object(arr))) return; var _arr = []; var _n = true; var _d = false; var _e = undefined; try { for (var _i = arr[Symbol.iterator](), _s; !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }

function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); if (enumerableOnly) symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); keys.push.apply(keys, symbols); } return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(Object(source), true).forEach(function (key) { _defineProperty(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && Symbol.iterator in Object(iter)) return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

var _wp$components = wp.components,
    SelectControl = _wp$components.SelectControl,
    ComboboxControl = _wp$components.ComboboxControl;
var useSelect = wp.data.useSelect;
var _wp$element = wp.element,
    useState = _wp$element.useState,
    useMemo = _wp$element.useMemo;
var __ = wp.i18n.__;
var _lodash = lodash,
    groupBy = _lodash.groupBy,
    flatMap = _lodash.flatMap,
    repeat = _lodash.repeat,
    unescape = _lodash.unescape,
    debounce = _lodash.debounce,
    find = _lodash.find;
var maxEntriesForSelect = 25; // Helper to build indent items based on tree structure.

var getOptionsFromTree = function getOptionsFromTree(tree) {
  var level = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 0;
  return flatMap(tree, function (treeNode) {
    return [{
      value: treeNode.id,
      label: repeat('— ', level) + unescape(treeNode.name)
    }].concat(_toConsumableArray(getOptionsFromTree(treeNode.children || [], level + 1)));
  });
}; // Build a tree from a list of hierarchical terms based on their parent values.


var buildTermsTree = function buildTermsTree(flatTerms) {
  var flatTermsWithParentAndChildren = flatTerms.map(function (term) {
    return _objectSpread({
      children: [],
      parent: null
    }, term);
  });
  var termsByParent = groupBy(flatTermsWithParentAndChildren, 'parent');

  if (termsByParent.null && termsByParent.null.length) {
    return flatTermsWithParentAndChildren;
  }

  var fillWithChildren = function fillWithChildren(terms) {
    return terms.map(function (term) {
      var children = termsByParent[term.id];
      return _objectSpread(_objectSpread({}, term), {}, {
        children: children && children.length ? fillWithChildren(children) : []
      });
    });
  };

  return fillWithChildren(termsByParent['0'] || []);
};

function ForumPicker(_ref) {
  var value = _ref.value,
      onChange = _ref.onChange;

  var _useState = useState(false),
      _useState2 = _slicedToArray(_useState, 2),
      fieldValue = _useState2[0],
      setFieldValue = _useState2[1];

  var isSearching = fieldValue;
  var postTypeSlug = bbpBlocks.data.forum_post_type; // Select the available forums from the REST API.

  var _useSelect = useSelect(function (select) {
    var _select = select('core'),
        getEntityRecords = _select.getEntityRecords,
        getEntityRecord = _select.getEntityRecord;

    var query = {
      per_page: 100,
      order: 'asc',
      post_type: postTypeSlug,
      _fields: 'id,title,parent'
    }; // Perform a search when the field is not default or empty.

    if (isSearching) {
      query.search = fieldValue;
    }

    return {
      // Ensure we always have the currently selected forum's data.
      currentForum: getEntityRecord('postType', postTypeSlug, value),
      selectOptions: getEntityRecords('postType', postTypeSlug, query)
    };
  }, [fieldValue]),
      selectOptions = _useSelect.selectOptions,
      currentForum = _useSelect.currentForum; // Update list whenever the fieldValue changes.
  // When the field has changed, update the inputValue, triggering a search.


  var handleKeydown = function handleKeydown(inputValue) {
    setFieldValue(inputValue);
  }; // Update the options whenever the selectOptions returned from the REST API change.


  var availableOptions = useMemo(function () {
    if (!selectOptions) {
      return [];
    }

    var tree = selectOptions.map(function (item) {
      return {
        id: item.id,
        name: item.title.rendered,
        parent: item.parent
      };
    }); // Build a hierarchical tree when not searching.

    if (!isSearching) {
      tree = buildTermsTree(tree);
    }

    var opts = getOptionsFromTree(tree); // Ensure the current forum is in the options list.

    var optsHasForum = find(opts, function (item) {
      return item.value === currentForum.id;
    });

    if (currentForum && !optsHasForum) {
      opts.unshift({
        value: value,
        label: currentForum.title.rendered
      });
    }

    return opts;
  }, [selectOptions]);

  var selectLabel = __('Forum'); // Display a regular select or searchable combobox.


  if (bbpBlocks.data.forum_count <= maxEntriesForSelect) {
    return /*#__PURE__*/React.createElement(SelectControl, {
      label: selectLabel,
      labelPosition: "top",
      value: value,
      options: availableOptions,
      onChange: onChange
    });
  } else {
    return /*#__PURE__*/React.createElement(ComboboxControl, {
      className: "editor-page-attributes__parent",
      label: selectLabel,
      value: value,
      options: availableOptions,
      onFilterValueChange: debounce(handleKeydown, 300),
      onChange: onChange
    });
  }
}

var _default = ForumPicker;
exports.default = _default;
},{}],"components/replyPicker.jsx":[function(require,module,exports) {
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var TextControl = wp.components.TextControl;
var __ = wp.i18n.__;

function ReplyPicker(_ref) {
  var value = _ref.value,
      onChange = _ref.onChange;
  return /*#__PURE__*/React.createElement(TextControl, {
    label: __('Reply ID'),
    type: "number",
    value: value,
    onChange: onChange
  });
}

var _default = ReplyPicker;
exports.default = _default;
},{}],"components/topicPicker.jsx":[function(require,module,exports) {
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var TextControl = wp.components.TextControl;
var __ = wp.i18n.__;

function TopicPicker(_ref) {
  var value = _ref.value,
      onChange = _ref.onChange;
  return /*#__PURE__*/React.createElement(TextControl, {
    label: __('Topic ID'),
    type: "number",
    value: value,
    onChange: onChange
  });
}

var _default = TopicPicker;
exports.default = _default;
},{}],"components/topicTagPicker.jsx":[function(require,module,exports) {
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var TextControl = wp.components.TextControl;
var __ = wp.i18n.__;

function TopicTagPicker(_ref) {
  var value = _ref.value,
      onChange = _ref.onChange;
  return /*#__PURE__*/React.createElement(TextControl, {
    label: __('Topic Tag ID'),
    type: "number",
    value: value,
    onChange: onChange
  });
}

var _default = TopicTagPicker;
exports.default = _default;
},{}],"components/viewPicker.jsx":[function(require,module,exports) {
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var TextControl = wp.components.TextControl;
var __ = wp.i18n.__;

function ViewPicker(_ref) {
  var value = _ref.value,
      onChange = _ref.onChange;
  return /*#__PURE__*/React.createElement(TextControl, {
    label: __('View'),
    placeholder: __('e.g. `popular` or `no-replies`'),
    value: value,
    onChange: onChange
  });
}

var _default = ViewPicker;
exports.default = _default;
},{}],"blocks.jsx":[function(require,module,exports) {
"use strict";

var _forumPicker = _interopRequireDefault(require("./components/forumPicker"));

var _replyPicker = _interopRequireDefault(require("./components/replyPicker"));

var _topicPicker = _interopRequireDefault(require("./components/topicPicker"));

var _topicTagPicker = _interopRequireDefault(require("./components/topicTagPicker"));

var _viewPicker = _interopRequireDefault(require("./components/viewPicker"));

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

/* global bbpBlocks */
var registerBlockType = wp.blocks.registerBlockType;
var _wp$components = wp.components,
    Placeholder = _wp$components.Placeholder,
    TextControl = _wp$components.TextControl;
var BlockIcon = wp.blockEditor.BlockIcon;
var __ = wp.i18n.__;

/* Dashicons most relevant to us for use:
buddicons-activity        activity
buddicons-bbpress-logo    bbPress logo
buddicons-buddypress-logo BuddyPress logo
buddicons-community       community
buddicons-forums          forums
buddicons-friends         friends
buddicons-groups          groups
buddicons-pm              private message
buddicons-replies         replies
buddicons-topics          topics
buddicons-tracking        tracking
*/
// Replaces [bbp-forum-index] – This will display your entire forum index.
registerBlockType('bbpress/forum-index', {
  title: __('Forums List'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-forums"
      }),
      label: __('bbPress Forum Index'),
      instructions: __('This will display your entire forum index.')
    });
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-forum-form] – Display the ‘New Forum’ form.

registerBlockType('bbpress/forum-form', {
  title: __('New Forum Form'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-forums"
      }),
      label: __('bbPress New Forum Form'),
      instructions: __('Display the ‘New Forum’ form.')
    });
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-single-forum id=$forum_id] – Display a single forums topics. eg. [bbp-single-forum id=32]

registerBlockType('bbpress/forum', {
  title: __('Single Forum'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {
    id: {
      //	type: 'number', // for some reason neither `number` nor `integer` works here.
      default: 0
    }
  },
  edit: function edit(props) {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-forums"
      }),
      label: __('bbPress Single Forum!'),
      instructions: __('Display a single forum’s topics!')
    }, /*#__PURE__*/React.createElement(_forumPicker.default, {
      value: props.attributes.id,
      options: bbpBlocks.data.forums,
      onChange: function onChange(id) {
        return props.setAttributes({
          id: id
        });
      }
    }));
  },
  save: function save() {
    return null;
  }
}); // Topics
// Replaces [bbp-topic-index] – Display the most recent 15 topics across all your forums with pagination.

registerBlockType('bbpress/topic-index', {
  title: __('Recent Topics'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-topics"
      }),
      label: __('bbPress Recent Topics'),
      instructions: __('Display the most recent 15 topics across all forums with pagination.')
    });
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-topic-form] – Display the ‘New Topic’ form where you can choose from a drop down menu the forum that this topic is to be associated with.
// Replaces [bbp-topic-form forum_id=$forum_id] – Display the ‘New Topic Form’ for a specific forum ID.

registerBlockType('bbpress/topic-form', {
  title: __('New Topic Form'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {
    forum_id: {
      //	type: 'number', // for some reason neither `number` nor `integer` works here.
      default: 0
    }
  },
  edit: function edit(props) {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-topics"
      }),
      label: __('bbPress New Topic Form'),
      instructions: __('Display a form to start a new topic.')
    }, /*#__PURE__*/React.createElement(_forumPicker.default, {
      value: props.attributes.forum_id,
      options: bbpBlocks.data.forums,
      onChange: function onChange(forum_id) {
        return props.setAttributes({
          forum_id: forum_id
        });
      }
    }));
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-single-topic id=$topic_id] – Display a single topic. eg. [bbp-single-topic id=4096]

registerBlockType('bbpress/single-topic', {
  title: __('Single Topic'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {
    id: {
      default: ''
    }
  },
  edit: function edit(props) {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-topics"
      }),
      label: __('bbPress Single Topic'),
      instructions: __('Display a single topic.')
    }, /*#__PURE__*/React.createElement(_topicPicker.default, {
      value: props.attributes.id,
      onChange: function onChange(id) {
        return props.setAttributes({
          id: id
        });
      }
    }));
  },
  save: function save() {
    return null;
  }
}); // Replies
// Replaces [bbp-reply-form] – Display the ‘New Reply’ form.

/* Unsure how well this one works -- submissions generate a `Error: Topic ID is missing.` */

registerBlockType('bbpress/reply-form', {
  title: __('New Reply Form'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-replies"
      }),
      label: __('bbPress New Reply Form'),
      instructions: __('Display the ‘New Reply’ form.')
    });
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-single-reply id=$reply_id] – Display a single reply eg. [bbp-single-reply id=32768]

registerBlockType('bbpress/single-reply', {
  title: __('Single Reply'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {
    id: {
      default: ''
    }
  },
  edit: function edit(props) {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-replies"
      }),
      label: __('bbPress Single Reply'),
      instructions: __('Display a single reply.')
    }, /*#__PURE__*/React.createElement(_replyPicker.default, {
      value: props.attributes.id,
      onChange: function onChange(id) {
        return props.setAttributes({
          id: id
        });
      }
    }));
  },
  save: function save() {
    return null;
  }
}); // Topic Tags
// Replaces [bbp-topic-tags] – Display a tag cloud of all topic tags.

registerBlockType('bbpress/topic-tags', {
  title: __('Topic Tag Cloud'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "buddicons-topics"
      }),
      label: __('bbPress Topic Tag Cloud'),
      instructions: __('Display a tag cloud of all topic tags.')
    });
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-single-tag id=$tag_id] – Display a list of all topics associated with a specific tag. eg. [bbp-single-tag id=64]

registerBlockType('bbpress/single-tag', {
  title: __('Single Topic Tag'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {
    id: {
      default: ''
    }
  },
  edit: function edit(props) {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "tag"
      }),
      label: __('bbPress Single Topic Tag'),
      instructions: __('Display a list of all topics associated with a specific topic tag.')
    }, /*#__PURE__*/React.createElement(_topicTagPicker.default, {
      value: props.attributes.id,
      onChange: function onChange(id) {
        return props.setAttributes({
          id: id
        });
      }
    }));
  },
  save: function save() {
    return null;
  }
}); // Views
// Replaces [bbp-single-view] – Single view – Display topics associated with a specific view. Current included ‘views’ with bbPress are “popular” [bbp-single-view id=’popular’] and “No Replies” [bbp-single-view id=’no-replies’]

registerBlockType('bbpress/single-view', {
  title: __('Single View'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {
    id: {
      default: ''
    }
  },
  edit: function edit(props) {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "media-code"
      }),
      label: __('bbPress Single View'),
      instructions: __('Display the contents of a specific bbPress view.')
    }, /*#__PURE__*/React.createElement(_viewPicker.default, {
      value: props.attributes.id,
      onChange: function onChange(id) {
        return props.setAttributes({
          id: id
        });
      }
    }));
  },
  save: function save() {
    return null;
  }
}); // Search
// Replaces [bbp-search] – Display the search results for a given term.

registerBlockType('bbpress/search', {
  title: __('Search Results'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {
    search: {
      default: ''
    }
  },
  edit: function edit(props) {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "search"
      }),
      label: __('Search Results'),
      instructions: __('Display the search results for a given query.')
    }, /*#__PURE__*/React.createElement(TextControl, {
      label: __('Search Term'),
      value: props.attributes.search,
      onChange: function onChange(search) {
        return props.setAttributes({
          search: search
        });
      }
    }));
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-search-form] – Display the search form template.

registerBlockType('bbpress/search-form', {
  title: __('Search Form'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "search"
      }),
      label: __('Search Form'),
      instructions: __('Display the search form template.')
    });
  },
  save: function save() {
    return null;
  }
}); // Account
// Replaces [bbp-login] – Display the login screen.

registerBlockType('bbpress/login', {
  title: __('Login'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "admin-users"
      }),
      label: __('Login Screen'),
      instructions: __('Display the login screen.')
    });
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-register] – Display the register screen.

registerBlockType('bbpress/register', {
  title: __('Register'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "admin-users"
      }),
      label: __('Register Screen'),
      instructions: __('Display the register screen.')
    });
  },
  save: function save() {
    return null;
  }
}); // Replaces [bbp-lost-pass] – Display the lost password screen.

registerBlockType('bbpress/lost-pass', {
  title: __('Lost Password Form'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "admin-users"
      }),
      label: __('Lost Password Form'),
      instructions: __('Display the lost password screen.')
    });
  },
  save: function save() {
    return null;
  }
}); // Statistics
// Replaces [bbp-stats] – Display the forum statistics.

registerBlockType('bbpress/stats', {
  title: __('Forum Statistics'),
  icon: 'buddicons-bbpress-logo',
  category: 'common',
  attributes: {},
  edit: function edit() {
    return /*#__PURE__*/React.createElement(Placeholder, {
      icon: /*#__PURE__*/React.createElement(BlockIcon, {
        icon: "chart-line"
      }),
      label: __('bbPress Forum Statistics'),
      instructions: __('Display the forum statistics.')
    });
  },
  save: function save() {
    return null;
  }
});
},{"./components/forumPicker":"components/forumPicker.jsx","./components/replyPicker":"components/replyPicker.jsx","./components/topicPicker":"components/topicPicker.jsx","./components/topicTagPicker":"components/topicTagPicker.jsx","./components/viewPicker":"components/viewPicker.jsx"}],"../../../../../node_modules/parcel-bundler/src/builtins/hmr-runtime.js":[function(require,module,exports) {
var global = arguments[3];
var OVERLAY_ID = '__parcel__error__overlay__';
var OldModule = module.bundle.Module;

function Module(moduleName) {
  OldModule.call(this, moduleName);
  this.hot = {
    data: module.bundle.hotData,
    _acceptCallbacks: [],
    _disposeCallbacks: [],
    accept: function (fn) {
      this._acceptCallbacks.push(fn || function () {});
    },
    dispose: function (fn) {
      this._disposeCallbacks.push(fn);
    }
  };
  module.bundle.hotData = null;
}

module.bundle.Module = Module;
var checkedAssets, assetsToAccept;
var parent = module.bundle.parent;

if ((!parent || !parent.isParcelRequire) && typeof WebSocket !== 'undefined') {
  var hostname = "" || location.hostname;
  var protocol = location.protocol === 'https:' ? 'wss' : 'ws';
  var ws = new WebSocket(protocol + '://' + hostname + ':' + "57546" + '/');

  ws.onmessage = function (event) {
    checkedAssets = {};
    assetsToAccept = [];
    var data = JSON.parse(event.data);

    if (data.type === 'update') {
      var handled = false;
      data.assets.forEach(function (asset) {
        if (!asset.isNew) {
          var didAccept = hmrAcceptCheck(global.parcelRequire, asset.id);

          if (didAccept) {
            handled = true;
          }
        }
      }); // Enable HMR for CSS by default.

      handled = handled || data.assets.every(function (asset) {
        return asset.type === 'css' && asset.generated.js;
      });

      if (handled) {
        console.clear();
        data.assets.forEach(function (asset) {
          hmrApply(global.parcelRequire, asset);
        });
        assetsToAccept.forEach(function (v) {
          hmrAcceptRun(v[0], v[1]);
        });
      } else if (location.reload) {
        // `location` global exists in a web worker context but lacks `.reload()` function.
        location.reload();
      }
    }

    if (data.type === 'reload') {
      ws.close();

      ws.onclose = function () {
        location.reload();
      };
    }

    if (data.type === 'error-resolved') {
      console.log('[parcel] ✨ Error resolved');
      removeErrorOverlay();
    }

    if (data.type === 'error') {
      console.error('[parcel] 🚨  ' + data.error.message + '\n' + data.error.stack);
      removeErrorOverlay();
      var overlay = createErrorOverlay(data);
      document.body.appendChild(overlay);
    }
  };
}

function removeErrorOverlay() {
  var overlay = document.getElementById(OVERLAY_ID);

  if (overlay) {
    overlay.remove();
  }
}

function createErrorOverlay(data) {
  var overlay = document.createElement('div');
  overlay.id = OVERLAY_ID; // html encode message and stack trace

  var message = document.createElement('div');
  var stackTrace = document.createElement('pre');
  message.innerText = data.error.message;
  stackTrace.innerText = data.error.stack;
  overlay.innerHTML = '<div style="background: black; font-size: 16px; color: white; position: fixed; height: 100%; width: 100%; top: 0px; left: 0px; padding: 30px; opacity: 0.85; font-family: Menlo, Consolas, monospace; z-index: 9999;">' + '<span style="background: red; padding: 2px 4px; border-radius: 2px;">ERROR</span>' + '<span style="top: 2px; margin-left: 5px; position: relative;">🚨</span>' + '<div style="font-size: 18px; font-weight: bold; margin-top: 20px;">' + message.innerHTML + '</div>' + '<pre>' + stackTrace.innerHTML + '</pre>' + '</div>';
  return overlay;
}

function getParents(bundle, id) {
  var modules = bundle.modules;

  if (!modules) {
    return [];
  }

  var parents = [];
  var k, d, dep;

  for (k in modules) {
    for (d in modules[k][1]) {
      dep = modules[k][1][d];

      if (dep === id || Array.isArray(dep) && dep[dep.length - 1] === id) {
        parents.push(k);
      }
    }
  }

  if (bundle.parent) {
    parents = parents.concat(getParents(bundle.parent, id));
  }

  return parents;
}

function hmrApply(bundle, asset) {
  var modules = bundle.modules;

  if (!modules) {
    return;
  }

  if (modules[asset.id] || !bundle.parent) {
    var fn = new Function('require', 'module', 'exports', asset.generated.js);
    asset.isNew = !modules[asset.id];
    modules[asset.id] = [fn, asset.deps];
  } else if (bundle.parent) {
    hmrApply(bundle.parent, asset);
  }
}

function hmrAcceptCheck(bundle, id) {
  var modules = bundle.modules;

  if (!modules) {
    return;
  }

  if (!modules[id] && bundle.parent) {
    return hmrAcceptCheck(bundle.parent, id);
  }

  if (checkedAssets[id]) {
    return;
  }

  checkedAssets[id] = true;
  var cached = bundle.cache[id];
  assetsToAccept.push([bundle, id]);

  if (cached && cached.hot && cached.hot._acceptCallbacks.length) {
    return true;
  }

  return getParents(global.parcelRequire, id).some(function (id) {
    return hmrAcceptCheck(global.parcelRequire, id);
  });
}

function hmrAcceptRun(bundle, id) {
  var cached = bundle.cache[id];
  bundle.hotData = {};

  if (cached) {
    cached.hot.data = bundle.hotData;
  }

  if (cached && cached.hot && cached.hot._disposeCallbacks.length) {
    cached.hot._disposeCallbacks.forEach(function (cb) {
      cb(bundle.hotData);
    });
  }

  delete bundle.cache[id];
  bundle(id);
  cached = bundle.cache[id];

  if (cached && cached.hot && cached.hot._acceptCallbacks.length) {
    cached.hot._acceptCallbacks.forEach(function (cb) {
      cb();
    });

    return true;
  }
}
},{}]},{},["../../../../../node_modules/parcel-bundler/src/builtins/hmr-runtime.js","blocks.jsx"], null)
//# sourceMappingURL=blocks.js.map