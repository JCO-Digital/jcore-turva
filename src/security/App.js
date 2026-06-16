import { TabPanel } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import GeneralTab from './components/GeneralTab';
import CspTab from './components/CspTab';
import PermissionsTab from './components/PermissionsTab';
import ReportsTab from './components/ReportsTab';

const TAB_NAMES = [ 'general', 'csp', 'permissions', 'reports' ];

function getTabFromUrl() {
	const tab = new URLSearchParams( window.location.search ).get( 'tab' );
	return TAB_NAMES.includes( tab ) ? tab : 'general';
}

function setTabInUrl( tabName ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'tab', tabName );
	window.history.replaceState( null, '', url.toString() );
}

export default function App() {
	const [ isMobile, setIsMobile ] = useState( () => window.innerWidth < 782 );

	useEffect( () => {
		const mql = window.matchMedia( '(max-width: 781px)' );
		const handler = ( e ) => setIsMobile( e.matches );
		mql.addEventListener( 'change', handler );
		return () => mql.removeEventListener( 'change', handler );
	}, [] );

	const tabs = [
		{ name: 'general', title: __( 'General', 'jcore-turva' ) },
		{
			name: 'csp',
			title: isMobile
				? __( 'CSP', 'jcore-turva' )
				: __( 'Content Security Policy', 'jcore-turva' ),
		},
		{
			name: 'permissions',
			title: isMobile
				? __( 'Permissions', 'jcore-turva' )
				: __( 'Permissions Policy', 'jcore-turva' ),
		},
		{
			name: 'reports',
			title: isMobile
				? __( 'Reports', 'jcore-turva' )
				: __( 'Violation Reports', 'jcore-turva' ),
		},
	];

	return (
		<div className="wrap jcore-turva">
			<h1>{ __( 'Security', 'jcore-turva' ) }</h1>
			<hr className="wp-header-end" />
			<TabPanel
				tabs={ tabs }
				initialTabName={ getTabFromUrl() }
				onSelect={ setTabInUrl }
			>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'general':
							return <GeneralTab />;
						case 'csp':
							return <CspTab />;
						case 'permissions':
							return <PermissionsTab />;
						case 'reports':
							return <ReportsTab />;
					}
				} }
			</TabPanel>
		</div>
	);
}
