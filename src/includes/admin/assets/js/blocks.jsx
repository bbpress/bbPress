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

import ForumPicker    from './components/forumPicker';
import ReplyPicker    from './components/replyPicker';
import TopicPicker    from './components/topicPicker';
import TopicTagPicker from './components/topicTagPicker';
import ViewPicker     from './components/viewPicker';

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
registerBlockType( 'bbpress/topic-index', {
	title: __( 'Recent Topics' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-topics" /> }
				label={ __( 'bbPress Recent Topics' ) }
				instructions={ __( 'Display the most recent 15 topics across all forums with pagination.' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-topic-form] – Display the ‘New Topic’ form where you can choose from a drop down menu the forum that this topic is to be associated with.
// Replaces [bbp-topic-form forum_id=$forum_id] – Display the ‘New Topic Form’ for a specific forum ID.
registerBlockType( 'bbpress/topic-form', {
	title: __( 'New Topic Form' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		forum_id: {
		//	type: 'number', // for some reason neither `number` nor `integer` works here.
			default: 0,
		}
	},

	edit: function( props ) {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-topics" /> }
				label={ __( 'bbPress New Topic Form' ) }
				instructions={ __( 'Display a form to start a new topic.' ) }
			>
				<ForumPicker
					value={ props.attributes.forum_id }
					options={ bbpBlocks.data.forums }
					onChange={ forum_id => props.setAttributes( { forum_id } ) }
				/>
			</Placeholder>
		);
	},

	save: () => null
} );

// Replaces [bbp-single-topic id=$topic_id] – Display a single topic. eg. [bbp-single-topic id=4096]
registerBlockType( 'bbpress/single-topic', {
	title: __( 'Single Topic' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		id: {
			default: '',
		}
	},

	edit: function( props ) {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-topics" /> }
				label={ __( 'bbPress Single Topic' ) }
				instructions={ __( 'Display a single topic.' ) }
			>
				<TopicPicker
					value={ props.attributes.id }
					onChange={ id => props.setAttributes( { id } ) }
				/>
			</Placeholder>
		);
	},

	save: () => null
} );

// Replies
// Replaces [bbp-reply-form] – Display the ‘New Reply’ form.
/* Unsure how well this one works -- submissions generate a `Error: Topic ID is missing.` */
registerBlockType( 'bbpress/reply-form', {
	title: __( 'New Reply Form' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-replies" /> }
				label={ __( 'bbPress New Reply Form' ) }
				instructions={ __( 'Display the ‘New Reply’ form.' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-single-reply id=$reply_id] – Display a single reply eg. [bbp-single-reply id=32768]
registerBlockType( 'bbpress/single-reply', {
	title: __( 'Single Reply' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		id: {
			default: '',
		}
	},

	edit: function( props ) {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-replies" /> }
				label={ __( 'bbPress Single Reply' ) }
				instructions={ __( 'Display a single reply.' ) }
			>
				<ReplyPicker
					value={ props.attributes.id }
					onChange={ id => props.setAttributes( { id } ) }
				/>
			</Placeholder>
		);
	},

	save: () => null
} );

// Topic Tags
// Replaces [bbp-topic-tags] – Display a tag cloud of all topic tags.
registerBlockType( 'bbpress/topic-tags', {
	title: __( 'Topic Tag Cloud' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon={ <BlockIcon icon="buddicons-topics" /> }
				label={ __( 'bbPress Topic Tag Cloud' ) }
				instructions={ __( 'Display a tag cloud of all topic tags.' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-single-tag id=$tag_id] – Display a list of all topics associated with a specific tag. eg. [bbp-single-tag id=64]
registerBlockType( 'bbpress/single-tag', {
	title: __( 'Single Topic Tag' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		id: {
			default: '',
		}
	},

	edit: function( props ) {
		return (
			<Placeholder
				icon={ <BlockIcon icon="tag" /> }
				label={ __( 'bbPress Single Topic Tag' ) }
				instructions={ __( 'Display a list of all topics associated with a specific topic tag.' ) }
			>
				<TopicTagPicker
					value={ props.attributes.id }
					onChange={ id => props.setAttributes( { id } ) }
				/>
			</Placeholder>
		);
	},

	save: () => null
} );

// Views
// Replaces [bbp-single-view] – Single view – Display topics associated with a specific view. Current included ‘views’ with bbPress are “popular” [bbp-single-view id=’popular’] and “No Replies” [bbp-single-view id=’no-replies’]
registerBlockType( 'bbpress/single-view', {
	title: __( 'Single View' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		id: {
			default: '',
		}
	},

	edit: function( props ) {
		return (
			<Placeholder
				icon={ <BlockIcon icon="media-code" /> }
				label={ __( 'bbPress Single View' ) }
				instructions={ __( 'Display the contents of a specific bbPress view.' ) }
			>
				<ViewPicker
					value={ props.attributes.id }
					onChange={ id => props.setAttributes( { id } ) }
				/>
			</Placeholder>
		);
	},

	save: () => null
} );

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
