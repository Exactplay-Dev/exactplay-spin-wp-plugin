/**
 * Block editor UI for the "Demo Game" and "Demo Game Grid" blocks.
 *
 * Plain JavaScript using the wp.* globals, so there is no build step.
 * Block metadata (attributes, supports, title) comes from each block.json.
 */
( function ( wp, config ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, createInterpolateElement, Fragment, useEffect, useState } = wp.element;
	const { __, _n, sprintf } = wp.i18n;
	const { BlockControls, InspectorControls, useBlockProps } = wp.blockEditor;
	const {
		Button,
		ExternalLink,
		Notice,
		PanelBody,
		Placeholder,
		RangeControl,
		SearchControl,
		SelectControl,
		Spinner,
		ToggleControl,
		ToolbarButton,
		ToolbarGroup,
	} = wp.components;
	const apiFetch = wp.apiFetch;
	const ServerSideRender = wp.serverSideRender;
	const { addQueryArgs } = wp.url;

	const options = config.options || {};
	const PAGE_SIZE = 24;
	const CONTROL_PROPS = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

	const gameIcon = el(
		'svg',
		{ viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
		el( 'path', {
			d: 'M5 5h11a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 1.5a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5V7a.5.5 0 0 0-.5-.5H5ZM6 9h2.5v6H6V9Zm3.25 0h2.5v6h-2.5V9Zm3.25 0H15v6h-2.5V9ZM20 8.2V13h-1.5v1.5h2a1 1 0 0 0 1-1V8.2a1.5 1.5 0 1 0-1.5 0Z',
		} )
	);

	const gridIcon = el(
		'svg',
		{ viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
		el( 'path', {
			d: 'M4 4h7v7H4V4Zm1.5 1.5v4h4v-4h-4ZM13 4h7v7h-7V4Zm1.5 1.5v4h4v-4h-4ZM4 13h7v7H4v-7Zm1.5 1.5v4h4v-4h-4ZM13 13h7v7h-7v-7Zm3 1.8v3.4l2.8-1.7-2.8-1.7Z',
		} )
	);

	/**
	 * Converts a { value: label } map into SelectControl options with a leading "default" choice.
	 *
	 * @param {Object} map   Option labels keyed by value.
	 * @param {string} label Label for the empty value.
	 * @return {Array} Options.
	 */
	function withDefault( map, label ) {
		return [ { value: '', label: label || __( 'Site default', 'exactplay-spin' ) } ].concat(
			Object.keys( map || {} ).map( ( value ) => ( { value, label: map[ value ] } ) )
		);
	}

	/**
	 * Unique key for a game.
	 *
	 * @param {Object} game Game with provider and slug.
	 * @return {string} Key.
	 */
	function gameKey( game ) {
		return game.provider + '/' + game.slug;
	}

	let providersRequest = null;

	/**
	 * Loads the provider list once per editor session.
	 *
	 * @return {Array} Providers.
	 */
	function useProviders() {
		const [ providers, setProviders ] = useState( [] );

		useEffect( () => {
			if ( ! providersRequest ) {
				providersRequest = apiFetch( { path: '/exactplay-spin/v1/providers' } ).catch( ( error ) => {
					providersRequest = null;
					throw error;
				} );
			}
			let active = true;
			providersRequest.then( ( list ) => active && setProviders( list ) ).catch( () => {} );
			return () => {
				active = false;
			};
		}, [] );

		return providers;
	}

	/**
	 * Provider options for a SelectControl.
	 *
	 * @param {Array} providers Providers.
	 * @return {Array} Options.
	 */
	function providerOptions( providers ) {
		return [ { value: '', label: __( 'All studios', 'exactplay-spin' ) } ].concat(
			providers.map( ( provider ) => ( {
				value: provider.slug,
				label: sprintf( '%1$s (%2$d)', provider.name, provider.count ),
			} ) )
		);
	}

	/**
	 * Links to the Exactplay Spin product page and the Plentyspins demo site.
	 *
	 * @return {Element} Element.
	 */
	function PoweredBy() {
		return el(
			'p',
			{ className: 'exactplay-spin-powered' },
			createInterpolateElement(
				__( 'Game catalog by <spin>Exactplay Spin</spin> · See it live on <demo>Plentyspins</demo>', 'exactplay-spin' ),
				{
					spin: el( ExternalLink, { href: config.productUrl } ),
					demo: el( ExternalLink, { href: config.demoUrl } ),
				}
			)
		);
	}

	/**
	 * Searchable, filterable list of games from the catalog.
	 *
	 * @param {Object}   props
	 * @param {Function} props.onSelect Called with the clicked game.
	 * @param {Array}    props.selected Games already chosen (shown as pressed).
	 * @return {Element} Element.
	 */
	function GamePicker( { onSelect, selected } ) {
		const providers = useProviders();
		const [ search, setSearch ] = useState( '' );
		const [ query, setQuery ] = useState( '' );
		const [ provider, setProvider ] = useState( '' );
		const [ page, setPage ] = useState( 1 );
		const [ result, setResult ] = useState( { games: [], total: 0, totalPages: 0, loading: true, error: '' } );
		const selectedKeys = ( selected || [] ).map( gameKey );

		// Wait for a pause in typing before searching.
		useEffect( () => {
			const timer = setTimeout( () => {
				setQuery( search.trim() );
				setPage( 1 );
			}, 300 );
			return () => clearTimeout( timer );
		}, [ search ] );

		useEffect( () => {
			let active = true;
			setResult( ( previous ) => Object.assign( {}, previous, { loading: true, error: '' } ) );

			apiFetch( {
				path: addQueryArgs( '/exactplay-spin/v1/games', { search: query, provider, page, per_page: PAGE_SIZE } ),
			} )
				.then( ( response ) => {
					if ( ! active ) {
						return;
					}
					setResult( ( previous ) => ( {
						games: page === 1 ? response.games : previous.games.concat( response.games ),
						total: response.total,
						totalPages: response.total_pages,
						loading: false,
						error: '',
					} ) );
				} )
				.catch( ( error ) => {
					if ( active ) {
						setResult( ( previous ) =>
							Object.assign( {}, previous, {
								loading: false,
								error: ( error && error.message ) || __( 'The game catalog could not be loaded.', 'exactplay-spin' ),
							} )
						);
					}
				} );

			return () => {
				active = false;
			};
		}, [ query, provider, page ] );

		return el(
			'div',
			{ className: 'exactplay-spin-picker' },
			el(
				'div',
				{ className: 'exactplay-spin-picker__filters' },
				el( SearchControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Search games', 'exactplay-spin' ),
					placeholder: __( 'Search games…', 'exactplay-spin' ),
					value: search,
					onChange: setSearch,
				} ),
				el(
					SelectControl,
					Object.assign( {}, CONTROL_PROPS, {
						label: __( 'Studio', 'exactplay-spin' ),
						hideLabelFromVision: true,
						value: provider,
						options: providerOptions( providers ),
						onChange: ( value ) => {
							setProvider( value );
							setPage( 1 );
						},
					} )
				)
			),
			result.error && el( Notice, { status: 'error', isDismissible: false }, result.error ),
			! result.loading &&
				! result.error &&
				el(
					'p',
					{ className: 'exactplay-spin-picker__count', 'aria-live': 'polite' },
					result.total
						? sprintf(
								/* translators: %s: number of games. */
								_n( '%s game', '%s games', result.total, 'exactplay-spin' ),
								result.total.toLocaleString()
						  )
						: __( 'No games found.', 'exactplay-spin' )
				),
			result.games.length > 0 &&
				el(
					'ul',
					{ className: 'exactplay-spin-picker__results' },
					result.games.map( ( game ) => {
						const isSelected = selectedKeys.indexOf( gameKey( game ) ) !== -1;
						return el(
							'li',
							{ key: gameKey( game ) },
							el(
								'button',
								{
									type: 'button',
									className: 'exactplay-spin-picker__game' + ( isSelected ? ' is-selected' : '' ),
									'aria-pressed': selected ? isSelected : undefined,
									onClick: () => onSelect( game ),
								},
								el(
									'span',
									{ className: 'exactplay-spin-picker__media' },
									game.images && game.images.landscape
										? el( 'img', { src: game.images.landscape, alt: '', loading: 'lazy' } )
										: null
								),
								el( 'span', { className: 'exactplay-spin-picker__name' }, game.name ),
								el( 'span', { className: 'exactplay-spin-picker__provider' }, game.provider_name )
							)
						);
					} )
				),
			result.loading && el( 'div', { className: 'exactplay-spin-picker__loading' }, el( Spinner ) ),
			! result.loading &&
				page < result.totalPages &&
				el(
					Button,
					{ variant: 'secondary', className: 'exactplay-spin-picker__more', onClick: () => setPage( page + 1 ) },
					__( 'Load more games', 'exactplay-spin' )
				),
			el( PoweredBy )
		);
	}

	/**
	 * Language, currency and layout overrides shared by both blocks.
	 *
	 * @param {Object}   props
	 * @param {Object}   props.attributes    Block attributes.
	 * @param {Function} props.setAttributes Attribute setter.
	 * @return {Element} Element.
	 */
	function LocalePanel( { attributes, setAttributes } ) {
		return el(
			PanelBody,
			{ title: __( 'Language and currency', 'exactplay-spin' ), initialOpen: false },
			el(
				SelectControl,
				Object.assign( {}, CONTROL_PROPS, {
					label: __( 'Game language', 'exactplay-spin' ),
					value: attributes.lang,
					options: withDefault( options.lang ),
					onChange: ( lang ) => setAttributes( { lang } ),
				} )
			),
			el(
				SelectControl,
				Object.assign( {}, CONTROL_PROPS, {
					label: __( 'Demo currency', 'exactplay-spin' ),
					value: attributes.currency,
					options: withDefault( options.currency ),
					onChange: ( currency ) => setAttributes( { currency } ),
				} )
			),
			el(
				SelectControl,
				Object.assign( {}, CONTROL_PROPS, {
					label: __( 'Game layout', 'exactplay-spin' ),
					value: attributes.channel,
					options: withDefault( options.channel ),
					onChange: ( channel ) => setAttributes( { channel } ),
				} )
			),
			el(
				'p',
				{ className: 'exactplay-spin-help' },
				createInterpolateElement(
					__( 'Site defaults are set under <a>Exactplay Spin → Settings</a>.', 'exactplay-spin' ),
					{ a: el( 'a', { href: config.settingsUrl, target: '_blank', rel: 'noopener' } ) }
				)
			)
		);
	}

	/**
	 * Server-rendered preview. Interaction is disabled so clicks select the block.
	 *
	 * @param {Object} props
	 * @param {string} props.block      Block name.
	 * @param {Object} props.attributes Block attributes.
	 * @return {Element} Element.
	 */
	function Preview( { block, attributes } ) {
		return el(
			'div',
			{ className: 'exactplay-spin-preview' },
			el( ServerSideRender, { block, attributes, skipBlockSupportAttributes: true } )
		);
	}

	/**
	 * Editor for the "Demo Game" block.
	 *
	 * @param {Object} props Block props.
	 * @return {Element} Element.
	 */
	function GameEdit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const hasGame = !! attributes.game;
		const [ picking, setPicking ] = useState( ! hasGame );

		if ( ! hasGame || picking ) {
			return el(
				'div',
				blockProps,
				el(
					Placeholder,
					{
						icon: gameIcon,
						label: __( 'Demo Game', 'exactplay-spin' ),
						instructions: __( 'Search the catalog and click a game to embed it.', 'exactplay-spin' ),
						className: 'exactplay-spin-placeholder',
					},
					el( GamePicker, {
						onSelect: ( game ) => {
							setAttributes( { provider: game.provider, game: game.slug, gameName: game.name } );
							setPicking( false );
						},
					} ),
					hasGame &&
						el( Button, { variant: 'tertiary', onClick: () => setPicking( false ) }, __( 'Cancel', 'exactplay-spin' ) )
				)
			);
		}

		return el(
			Fragment,
			null,
			el(
				BlockControls,
				null,
				el(
					ToolbarGroup,
					null,
					el( ToolbarButton, { onClick: () => setPicking( true ) }, __( 'Replace game', 'exactplay-spin' ) )
				)
			),
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Player', 'exactplay-spin' ) },
					el(
						'p',
						{ className: 'exactplay-spin-help' },
						sprintf(
							/* translators: %s: game name. */
							__( 'Embedding: %s', 'exactplay-spin' ),
							attributes.gameName || attributes.game
						)
					),
					el(
						SelectControl,
						Object.assign( {}, CONTROL_PROPS, {
							label: __( 'Loading', 'exactplay-spin' ),
							value: attributes.display,
							options: withDefault( options.display ),
							onChange: ( display ) => setAttributes( { display } ),
						} )
					),
					el(
						SelectControl,
						Object.assign( {}, CONTROL_PROPS, {
							label: __( 'Aspect ratio', 'exactplay-spin' ),
							value: attributes.aspectRatio,
							options: withDefault( options.aspectRatio ),
							onChange: ( aspectRatio ) => setAttributes( { aspectRatio } ),
						} )
					),
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Show title and fullscreen button', 'exactplay-spin' ),
						checked: attributes.showCaption,
						onChange: ( showCaption ) => setAttributes( { showCaption } ),
					} )
				),
				el( LocalePanel, { attributes, setAttributes } )
			),
			el( 'div', blockProps, el( Preview, { block: 'exactplay-spin/game', attributes } ) )
		);
	}

	/**
	 * Editor for the "Demo Game Grid" block.
	 *
	 * @param {Object} props Block props.
	 * @return {Element} Element.
	 */
	function GamesEdit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const providers = useProviders();
		const { source, games } = attributes;
		const isSelected = 'selected' === source;
		const [ picking, setPicking ] = useState( isSelected && games.length === 0 );

		const toggleGame = ( game ) => {
			const key = gameKey( game );
			const exists = games.some( ( item ) => gameKey( item ) === key );
			setAttributes( {
				games: exists
					? games.filter( ( item ) => gameKey( item ) !== key )
					: games.concat( [ { provider: game.provider, slug: game.slug, name: game.name } ] ),
			} );
		};

		const move = ( index, offset ) => {
			const next = games.slice();
			const item = next.splice( index, 1 )[ 0 ];
			next.splice( index + offset, 0, item );
			setAttributes( { games: next } );
		};

		const sourceHelp = {
			random: __( 'A fresh random selection every 15 minutes.', 'exactplay-spin' ),
			catalog: __( 'The same games every time, in catalog order.', 'exactplay-spin' ),
			selected: __( 'Only the games you choose, in your order.', 'exactplay-spin' ),
		};

		const inspector = el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Games', 'exactplay-spin' ) },
				el(
					SelectControl,
					Object.assign( {}, CONTROL_PROPS, {
						label: __( 'Show', 'exactplay-spin' ),
						value: source,
						options: [
							{ value: 'random', label: __( 'Random games', 'exactplay-spin' ) },
							{ value: 'catalog', label: __( 'Games from the catalog', 'exactplay-spin' ) },
							{ value: 'selected', label: __( 'Games I pick', 'exactplay-spin' ) },
						],
						help: sourceHelp[ source ],
						onChange: ( value ) => {
							setAttributes( { source: value } );
							setPicking( 'selected' === value && games.length === 0 );
						},
					} )
				),
				! isSelected &&
					el(
						SelectControl,
						Object.assign( {}, CONTROL_PROPS, {
							label: __( 'Studio', 'exactplay-spin' ),
							value: attributes.provider,
							options: providerOptions( providers ),
							onChange: ( provider ) => setAttributes( { provider } ),
						} )
					),
				! isSelected &&
					el(
						RangeControl,
						Object.assign( {}, CONTROL_PROPS, {
							label: __( 'Number of games', 'exactplay-spin' ),
							value: attributes.count,
							min: 1,
							max: 48,
							onChange: ( count ) => setAttributes( { count } ),
						} )
					),
				isSelected &&
					games.length > 0 &&
					el(
						'ol',
						{ className: 'exactplay-spin-selected' },
						games.map( ( game, index ) =>
							el(
								'li',
								{ key: gameKey( game ) },
								el( 'span', { className: 'exactplay-spin-selected__name' }, game.name || game.slug ),
								el( Button, {
									icon: 'arrow-up-alt2',
									label: __( 'Move up', 'exactplay-spin' ),
									size: 'small',
									disabled: index === 0,
									accessibleWhenDisabled: true,
									onClick: () => move( index, -1 ),
								} ),
								el( Button, {
									icon: 'arrow-down-alt2',
									label: __( 'Move down', 'exactplay-spin' ),
									size: 'small',
									disabled: index === games.length - 1,
									accessibleWhenDisabled: true,
									onClick: () => move( index, 1 ),
								} ),
								el( Button, {
									icon: 'no-alt',
									label: __( 'Remove', 'exactplay-spin' ),
									size: 'small',
									isDestructive: true,
									onClick: () => toggleGame( game ),
								} )
							)
						)
					),
				isSelected &&
					el(
						Button,
						{ variant: 'secondary', onClick: () => setPicking( true ) },
						__( 'Add or remove games', 'exactplay-spin' )
					)
			),
			el(
				PanelBody,
				{ title: __( 'Layout', 'exactplay-spin' ) },
				el(
					RangeControl,
					Object.assign( {}, CONTROL_PROPS, {
						label: __( 'Columns', 'exactplay-spin' ),
						value: attributes.columns,
						min: 1,
						max: 8,
						onChange: ( columns ) => setAttributes( { columns } ),
					} )
				),
				el(
					SelectControl,
					Object.assign( {}, CONTROL_PROPS, {
						label: __( 'Artwork', 'exactplay-spin' ),
						value: attributes.imageShape,
						options: [
							{ value: 'portrait', label: __( 'Portrait', 'exactplay-spin' ) },
							{ value: 'square', label: __( 'Square', 'exactplay-spin' ) },
							{ value: 'landscape', label: __( 'Landscape', 'exactplay-spin' ) },
						],
						onChange: ( imageShape ) => setAttributes( { imageShape } ),
					} )
				),
				el(
					SelectControl,
					Object.assign( {}, CONTROL_PROPS, {
						label: __( 'When a game is clicked', 'exactplay-spin' ),
						value: attributes.clickAction,
						options: withDefault( options.clickAction ),
						onChange: ( clickAction ) => setAttributes( { clickAction } ),
					} )
				),
				el( ToggleControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Show studio names', 'exactplay-spin' ),
					checked: attributes.showProvider,
					onChange: ( showProvider ) => setAttributes( { showProvider } ),
				} )
			),
			el( LocalePanel, { attributes, setAttributes } )
		);

		const toolbar =
			isSelected &&
			el(
				BlockControls,
				null,
				el(
					ToolbarGroup,
					null,
					el( ToolbarButton, { onClick: () => setPicking( ! picking ) }, __( 'Choose games', 'exactplay-spin' ) )
				)
			);

		if ( isSelected && ( picking || games.length === 0 ) ) {
			return el(
				Fragment,
				null,
				toolbar,
				inspector,
				el(
					'div',
					blockProps,
					el(
						Placeholder,
						{
							icon: gridIcon,
							label: __( 'Demo Game Grid', 'exactplay-spin' ),
							instructions: games.length
								? sprintf(
										/* translators: %d: number of selected games. */
										_n( '%d game selected. Click games to add or remove them.', '%d games selected. Click games to add or remove them.', games.length, 'exactplay-spin' ),
										games.length
								  )
								: __( 'Click games to add them to the grid.', 'exactplay-spin' ),
							className: 'exactplay-spin-placeholder',
						},
						el( GamePicker, {
							onSelect: ( game ) => {
								// Stay in picking mode after the first game is added.
								setPicking( true );
								toggleGame( game );
							},
							selected: games,
						} ),
						games.length > 0 &&
							el( Button, { variant: 'primary', onClick: () => setPicking( false ) }, __( 'Done', 'exactplay-spin' ) )
					)
				)
			);
		}

		return el(
			Fragment,
			null,
			toolbar,
			inspector,
			el( 'div', blockProps, el( Preview, { block: 'exactplay-spin/games', attributes } ) )
		);
	}

	registerBlockType( 'exactplay-spin/game', {
		icon: gameIcon,
		edit: GameEdit,
		save: () => null,
	} );

	registerBlockType( 'exactplay-spin/games', {
		icon: gridIcon,
		edit: GamesEdit,
		save: () => null,
	} );
} )( window.wp, window.exactplaySpinEditor || {} );
