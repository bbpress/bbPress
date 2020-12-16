/* global bbpBlocks */

const {
	registerBlockType
} = wp.blocks;

const {
	Placeholder,
	TextControl
} = wp.components;

const {
	BlockIcon
} = wp.blockEditor;

const {
	__
} = wp.i18n;

import ForumPicker from './components/forumPicker';

/*
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
registerBlockType( 'bbpress/forum-index', {
	title: __( 'Forums List' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-forums" /> }
				label={ __( 'bbPress Forum Index' ) }
				instructions={ __( 'This will display your entire forum index.' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-forum-form] – Display the ‘New Forum’ form.
registerBlockType( 'bbpress/forum-form', {
	title: __( 'New Forum Form' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-forums" /> }
				label={ __( 'bbPress New Forum Form' ) }
				instructions={ __( 'Display the ‘New Forum’ form.' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-single-forum id=$forum_id] – Display a single forums topics. eg. [bbp-single-forum id=32]
registerBlockType( 'bbpress/forum', {
	title: __( 'Single Forum' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		id: {
		//	type: 'number', // for some reason neither `number` nor `integer` works here.
			default: 0,
		}
	},

	edit: function( props ) {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-forums" /> }
				label={ __( 'bbPress Single Forum' ) }
				instructions={ __( 'Display a single forum’s topics.' ) }
			>
				<ForumPicker
					value={ props.attributes.id }
					options={ bbpBlocks.data.forums }
					onChange={ id => props.setAttributes( { id } ) }
				/>
			</Placeholder>
		);
	},

	save: () => null

} );

// Topics
// Replaces [bbp-topic-index] – Display the most recent 15 topics across all your forums with pagination.
// Replaces [bbp-topic-form] – Display the ‘New Topic’ form where you can choose from a drop down menu the forum that this topic is to be associated with.
// Replaces [bbp-topic-form forum_id=$forum_id] – Display the ‘New Topic Form’ for a specific forum ID.
// Replaces [bbp-single-topic id=$topic_id] – Display a single topic. eg. [bbp-single-topic id=4096]

// Replies
// Replaces [bbp-reply-form] – Display the ‘New Reply’ form.
// Replaces [bbp-single-reply id=$reply_id] – Display a single reply eg. [bbp-single-reply id=32768]

// Topic Tags
// Replaces [bbp-topic-tags] – Display a tag cloud of all topic tags.
// Replaces [bbp-single-tag id=$tag_id] – Display a list of all topics associated with a specific tag. eg. [bbp-single-tag id=64]

// Views
// Replaces [bbp-single-view] – Single view – Display topics associated with a specific view. Current included ‘views’ with bbPress are “popular” [bbp-single-view id=’popular’] and “No Replies” [bbp-single-view id=’no-replies’]

// Search
// Replaces [bbp-search] – Display the search results for a given term.
registerBlockType( 'bbpress/search', {
	title: __( 'Search Results' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		search: {
			default: ''
		}
	},

	edit: function( props ) {
		return (
			<Placeholder
				icon={ <BlockIcon icon="search" /> }
				label={ __( 'Search Results' ) }
				instructions={ __( 'Display the search results for a given query.' ) }
			>
				<TextControl
					label={ __( 'Search Term' ) }
					value={ props.attributes.search }
					onChange={ search => props.setAttributes( { search } ) }
				/>
			</Placeholder>
		);
	},

	save: () => null
} );

// Replaces [bbp-search-form] – Display the search form template.
registerBlockType( 'bbpress/search-form', {
	title: __( 'Search Form' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="search" /> }
				label={ __( 'Search Form' ) }
				instructions={ __( 'Display the search form template.' ) }
			/>
		);
	},

	save: () => null
} );

// Account
// Replaces [bbp-login] – Display the login screen.
registerBlockType( 'bbpress/login', {
	title: __( 'Login' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="admin-users" /> }
				label={ __( 'Login Screen' ) }
				instructions={ __( 'Display the login screen.' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-register] – Display the register screen.
registerBlockType( 'bbpress/register', {
	title: __( 'Register' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="admin-users" /> }
				label={ __( 'Register Screen' ) }
				instructions={ __( 'Display the register screen.' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-lost-pass] – Display the lost password screen.
registerBlockType( 'bbpress/lost-pass', {
	title: __( 'Lost Password Form' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="admin-users" /> }
				label={ __( 'Lost Password Form' ) }
				instructions={ __( 'Display the lost password screen.' ) }
			/>
		);
	},

	save: () => null
} );

// Statistics
// Replaces [bbp-stats] – Display the forum statistics.
registerBlockType( 'bbpress/stats', {
	title: __( 'Forum Statistics' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="chart-line" /> }
				label={ __( 'bbPress Forum Statistics' ) }
				instructions={ __( 'Display the forum statistics.' ) }
			/>
		);
	},

	save: () => null
} );


