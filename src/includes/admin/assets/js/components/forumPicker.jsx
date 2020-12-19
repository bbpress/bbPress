
const {
	SelectControl,
	ComboboxControl,
} = wp.components;

const {
	useSelect
} = wp.data;

const {
	useState,
	useMemo
} = wp.element;

const {
	__
} = wp.i18n;

const {
	groupBy,
	flatMap,
	repeat,
	unescape,
	debounce,
	find,
} = lodash;

const maxEntriesForSelect = 25;

// Helper to build an indented item list based on tree structure.
const getOptionsFromTree = ( tree, level = 0 ) => {
	return flatMap( tree, ( treeNode ) => [
		{
			value: treeNode.id,
			label: repeat( '— ', level ) + unescape( treeNode.name ),
		},
		...getOptionsFromTree( treeNode.children || [], level + 1 ),
	] );
};

// Build a tree from a list of hierarchical terms based on their parent values.
const buildTermsTree = ( flatTerms ) => {
	const flatTermsWithParentAndChildren = flatTerms.map( ( term ) => {
		return {
			children: [],
			parent: null,
			...term,
		};
	} );

	const termsByParent = groupBy( flatTermsWithParentAndChildren, 'parent' );
	if ( termsByParent.null && termsByParent.null.length ) {
		return flatTermsWithParentAndChildren;
	}
	const fillWithChildren = ( terms ) => {
		return terms.map( ( term ) => {
			const children = termsByParent[ term.id ];
			return {
				...term,
				children:
					children && children.length
						? fillWithChildren( children )
						: [],
			};
		} );
	};

	return fillWithChildren( termsByParent[ '0' ] || [] );
}

function ForumPicker( { value, onChange } ) {
	const [ fieldValue, setFieldValue ] = useState( false );
	const isSearching = fieldValue;
	const postTypeSlug = bbpBlocks.data.forum_post_type;

	// Select the available forums from the REST API.
	const { selectOptions, currentForum } = useSelect( ( select ) => {
		const { getEntityRecords, getEntityRecord } = select(
			'core'
		);
		const query = {
			per_page: 100,
			order: 'asc',
			post_type: postTypeSlug,
			_fields: 'id,title,parent',
		};
		// Perform a search when the field is not default or empty.
		if ( isSearching ) {
			query.search = fieldValue;
		}
		return {
			// Ensure we always have the currently selected forum's data.
			currentForum:  getEntityRecord( 'postType', postTypeSlug, value ),
			selectOptions: getEntityRecords( 'postType', postTypeSlug, query ),
		};
	}, [ fieldValue ] ); // Update list whenever the fieldValue changes.

	// When the field has changed, update the inputValue, triggering a search.
	const handleKeydown = ( inputValue ) => {
		setFieldValue( inputValue );
	};

	// Update the options whenever the selectOptions returned from the REST API change.
	const availableOptions = useMemo( () => {
		if ( ! selectOptions ){
			return [];
		}
		let tree = selectOptions.map( ( item ) => ( {
			id: item.id,
			name: item.title.rendered,
			parent: item.parent,
		} ) );

		// Build a hierarchical tree when not searching.
		if ( ! isSearching ) {
			tree = buildTermsTree( tree );
		}

		const opts = getOptionsFromTree( tree );

		// Ensure the current forum is in the options list.
		const optsHasForum = find(
			opts,
			( item ) => item.value === currentForum.id
		);
		if ( currentForum && ! optsHasForum ) {
			opts.unshift( {
				value,
				label: currentForum.title.rendered,
			} );
		}
		return opts;
	}, [ selectOptions ] );

	const selectLabel = __( 'Forum' );

	// Display a regular select or searchable combobox.
	if ( bbpBlocks.data.forum_count <= maxEntriesForSelect ) {
		return (
			<SelectControl
				label={ selectLabel }
				labelPosition="top"
				value={ value }
				options={ availableOptions }
				onChange={ onChange }
			/>
		);
	} else {
		return (
			<ComboboxControl
				className="editor-page-attributes__parent"
				label={ selectLabel }
				value={ value }
				options={ availableOptions }
				onFilterValueChange={ debounce( handleKeydown, 300 ) }
				onChange={ onChange }
			/>
		)
	}
}

export default ForumPicker;
