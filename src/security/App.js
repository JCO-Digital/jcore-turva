import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import GeneralTab from './components/GeneralTab';
import CspTab from './components/CspTab';
import PermissionsTab from './components/PermissionsTab';
import ReportsTab from './components/ReportsTab';

const TABS = [
	{ name: 'general', title: __( 'General', 'jcore-turva' ) },
	{ name: 'csp', title: __( 'Content Security Policy', 'jcore-turva' ) },
	{ name: 'permissions', title: __( 'Permissions Policy', 'jcore-turva' ) },
	{ name: 'reports', title: __( 'Violation Reports', 'jcore-turva' ) },
];

const TAB_NAMES = TABS.map( ( t ) => t.name );

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
	return (
		<div className="wrap jcore-turva">
			<h1>{ __( 'Security', 'jcore-turva' ) }</h1>
			<hr className="wp-header-end" />
			<TabPanel
				tabs={ TABS }
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
