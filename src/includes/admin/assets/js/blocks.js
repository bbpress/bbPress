(function() {
	if ( typeof wp === 'undefined' || !wp.blocks || !bbpBlocksJS ) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const {
		createElement: el,
		useEffect,
		useRef,
		Fragment,
	} = wp.element;
	const blockEditor = wp.blockEditor || wp.editor;

	if ( !blockEditor || !blockEditor.useBlockProps ) {
		return;
	}

	const { InspectorControls, useBlockProps } = blockEditor;
	const {
		PanelBody,
		SelectControl,
		TextControl,
	} = wp.components;
	const ServerSideRender = wp.serverSideRender;

	const blocks = Array.isArray( bbpBlocksJS.blocks ) ? bbpBlocksJS.blocks : [];
	const data = bbpBlocksJS.data || {};
	const forums = Array.isArray( data.forums ) ? data.forums : [];
	const views = Array.isArray( data.views ) ? data.views : [];
	const tags = Array.isArray( data.tags ) ? data.tags : [];
	const requiredIdSlugs = [ 'single-forum', 'single-topic', 'single-reply', 'single-tag', 'single-view' ];

	/**
	 * Normalize a block name to its slug (strip bbpress namespace).
	 *
	 * @param {string} blockName Full block name
	 * @returns {string} Slug without namespace
	 */
	function getBlockSlug( blockName ) {
		return blockName.replace( 'bbpress/', '' );
	}

	if ( !blocks.length ) {
		return;
	}

	/**
	 * Remove interactivity from server-rendered previews so the block panel stays in control.
	 *
	 * @param {HTMLElement} container Preview wrapper element
	 */
	function disablePreviewInteractivity( container ) {
		if ( !container || container.dataset.bbpDisabled === '1' ) {
			return;
		}

		container.dataset.bbpDisabled = '1';

		const scope = container.querySelector( '#bbpress-forums' ) || container;
		const interactive = scope.querySelectorAll( 'a, button, input, textarea, select' );

		interactive.forEach( disableInteractiveNode );

		const forms = scope.querySelectorAll( 'form' );

		forms.forEach( disableFormNode );
	}

	/**
	 * Make individual interactive nodes inert inside the preview.
	 *
	 * @param {HTMLElement} node Element to disable
	 */
	function disableInteractiveNode( node ) {
		node.setAttribute( 'tabindex', '-1' );
		node.setAttribute( 'aria-disabled', 'true' );
	}

	/**
	 * Mark forms as disabled for accessibility within the preview.
	 *
	 * @param {HTMLElement} node Form element to disable
	 */
	function disableFormNode( node ) {
		node.setAttribute( 'aria-disabled', 'true' );
	}

	/**
	 * Gate rendering until required attributes are present for the given block type.
	 *
	 * @param {string} blockName Full block name
	 * @param {Object} attributes Block attributes
	 * @returns {boolean} True if required attributes exist
	 */
	function hasRequiredAttributes( blockName, attributes ) {
		const slug = getBlockSlug( blockName );
		if ( requiredIdSlugs.includes( slug ) ) {
			return !!attributes.id;
		}
		return true;
	}

	/**
	 * Build Inspector controls per block type, wiring handlers to set attributes.
	 *
	 * @param {string} blockName Full block name (e.g., bbpress/single-forum)
	 * @param {Object} props Block props from the editor
	 * @returns {Array} Array of control elements
	 */
	function buildControls( blockName, props ) {
		const slug = getBlockSlug( blockName );
		const controls = [];

		/**
		 * Set a numeric attribute based on input/select values.
		 *
		 * @param {string} attrKey Attribute key to update
		 * @param {string|number} value Incoming value
		 */
		function setNumericAttribute( attrKey, value ) {
			props.setAttributes( { [ attrKey ]: parseInt( value, 10 ) || 0 } );
		}

		/**
		 * Set a string attribute based on input/select values.
		 *
		 * @param {string} attrKey Attribute key to update
		 * @param {string|number} value Incoming value
		 */
		function setStringAttribute( attrKey, value ) {
			const normalized = value ? String( value ) : '';
			props.setAttributes( { [ attrKey ]: normalized } );
		}

		if ( slug === 'single-forum' ) {
			controls.push(
				el( SelectControl, {
					key: 'forum-select',
					label: __( 'Forum', 'bbpress' ),
					value: props.attributes.id || 0,
					options: forums,
					onChange: function( id ) {
                        setNumericAttribute( 'id', id );
                    },
				} )
			);
		}

		if ( slug === 'single-topic' ) {
			controls.push(
				el( TextControl, {
					key: 'topic-id',
					label: __( 'Topic ID', 'bbpress' ),
					type: 'number',
					value: props.attributes.id || '',
					help: __( 'Enter the numeric ID of the topic to display.', 'bbpress' ),
					onChange: function( id ) {
                        setNumericAttribute( 'id', id );
                    },
				} )
			);
		}

		if ( slug === 'single-reply' ) {
			controls.push(
				el( TextControl, {
					key: 'reply-id',
					label: __( 'Reply ID', 'bbpress' ),
					type: 'number',
					value: props.attributes.id || '',
					help: __( 'Enter the numeric ID of the reply to display.', 'bbpress' ),
					onChange: function( id ) {
                        setNumericAttribute( 'id', id );
                    },
				} )
			);
		}

		if ( slug === 'single-tag' ) {
			controls.push(
				el( SelectControl, {
					key: 'tag-select',
					label: __( 'Topic Tag', 'bbpress' ),
					value: props.attributes.id || 0,
					options: tags,
					help: __( 'Select a topic tag.', 'bbpress' ),
					onChange: function( id ) {
                        setNumericAttribute( 'id', id );
                    },
				} )
			);
		}

		if ( slug === 'single-view' ) {
			controls.push(
				el( SelectControl, {
					key: 'view-select',
					label: __( 'View', 'bbpress' ),
					value: props.attributes.id || '',
					options: views,
					help: __( 'Select a topic view.', 'bbpress' ),
					onChange: function( id ) {
                        setStringAttribute( 'id', id );
                    },
				} )
			);
		}

		if ( slug === 'topic-form' ) {
			controls.push(
				el( SelectControl, {
					key: 'forum-select-topic-form',
					label: __( 'Forum (Optional)', 'bbpress' ),
					value: props.attributes.forum_id || 0,
					options: forums,
					help: __( 'Optionally select a forum for this topic form.', 'bbpress' ),
					onChange: function( id ) {
                        setNumericAttribute( 'forum_id', id );
                    },
				} )
			);
		}

		return controls;
	}

	/**
	 * Factory that returns the block edit component with preview + inspector wiring.
	 *
	 * @param {string} blockName Full block name
	 * @param {string} title Block title
	 * @param {string} description Block description
	 * @returns {Function} Edit component
	 */
	function createEditFunction( blockName, title, description ) {
		return function( props ) {
			const blockProps = useBlockProps( { className: 'bbpress-block' } );
			const previewRef = useRef( null );
			const controls   = buildControls( blockName, props );
			const canRender  = hasRequiredAttributes( blockName, props.attributes );
			const children   = [];

			if ( title ) {
				children.push( el( 'h3', { key: 'title', className: 'bbpress-block__title' }, title ) );
			}

			if ( description ) {
				children.push( el( 'p', { key: 'description', className: 'bbpress-block__description' }, description ) );
			}

			if ( canRender ) {
				children.push(
					el(
						'div',
						{ key: 'preview', className: 'bbpress-block__preview', ref: previewRef },
						el( ServerSideRender, { block: blockName, attributes: props.attributes } )
					)
				);
			} else {
				children.push(
					el( 'p', { key: 'placeholder', className: 'bbpress-block__placeholder' }, __( 'Configure block settings to see preview.', 'bbpress' ) )
				);
			}

			useEffect( handlePreviewMount, [ props.attributes ] );

			/**
			 * After render, disable interactivity inside the preview container.
			 */
			function handlePreviewMount() {
				if ( previewRef && previewRef.current ) {
					disablePreviewInteractivity( previewRef.current );
				}
			}

			const result = [
				controls.length > 0 ? el(
					InspectorControls,
					{ key: 'inspector' },
					el( PanelBody, { title: __( 'Block Settings', 'bbpress' ), initialOpen: true }, controls )
				) : null,
				el( 'div', Object.assign( { key: 'content' }, blockProps ), children )
			];

			return el( Fragment, {}, result );
		};
	}

	if ( !blocks.length ) {
		return;
	}

	blocks.forEach( registerBlock );

	/**
	 * Register a block from its metadata, wiring edit/save callbacks.
	 *
	 * @param {Object} meta Block metadata
	 */
	function registerBlock( meta ) {
		if ( !meta || !meta.name ) {
			return;
		}

		registerBlockType( meta.name, {
			edit: createEditFunction( meta.name, meta.title, meta.description ),
			save: getSaveFunction,
		} );
	}

	/**
	 * Server-rendered blocks save on the server; return null to signal no client save.
	 *
	 * @returns {null} No client-side save
	 */
	function getSaveFunction() {
		return null;
	}
})();