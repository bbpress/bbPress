/* global bbpBlocks */

const {
	registerBlockType
} = wp.blocks;

const {
	Placeholder,
	SelectControl
} = wp.components;

const {
	__
} = wp.i18n;

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

import '../css/blocks.scss';

// Replaces [bbp-forum-index] – This will display your entire forum index.
registerBlockType( 'bbpress/forum-index', {
	title: __( 'Forums List' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {},

	edit: function() {
		return (
			<Placeholder
				icon="buddicons-forums"
				label={ __( 'bbPress Forum Index' ) }
			/>
		);
	},

	save: () => null
} );

// Replaces [bbp-forum-form] – Display the ‘New Forum’ form.


// Replaces [bbp-single-forum id=$forum_id] – Display a single forums topics. eg. [bbp-single-forum id=32]
registerBlockType( 'bbpress/forum', {
	title: __( 'Single Forum' ),
	icon: 'buddicons-bbpress-logo',
	category: 'common',

	attributes: {
		id: {
			type: 'integer',
			default: 0,
		}
	},

	edit: function( props ) {
		function handleForumChange( id ) {
			props.setAttributes({ id })
		}

		return (
			<Placeholder
				icon="buddicons-forums"
				label={ __( 'bbPress Single Forum' ) }
			>
				<SelectControl
					label={ __( 'Forum' ) }
					labelPosition="top"
					value={ props.attributes.id }
					options={ bbpBlocks.data.forums }
					onChange={ handleForumChange }
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
// Replaces [bbp-search] – Display the search input form.
// Replaces [bbp-search-form] – Display the search form template.

// Account
// Replaces [bbp-login] – Display the login screen.
// Replaces [bbp-register] – Display the register screen.
// Replaces [bbp-lost-pass] – Display the lost password screen.

// Statistics
// Replaces [bbp-stats] – Display the forum statistics.


