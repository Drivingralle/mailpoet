import { ComponentProps } from 'react';
import { useSelect } from '@wordpress/data';
import { Platform } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import {
  ComplementaryArea,
  store as interfaceStore,
} from '@wordpress/interface';
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';
import { Header } from './header';
import { EmailSidebar } from './email';
import { BlockSidebar } from './block';
import { storeName, blockSidebarKey, emailSidebarKey } from '../../store';

// See:
//   https://github.com/WordPress/gutenberg/blob/5caeae34b3fb303761e3b9432311b26f4e5ea3a6/packages/edit-post/src/components/sidebar/plugin-sidebar/index.js
//   https://github.com/WordPress/gutenberg/blob/5caeae34b3fb303761e3b9432311b26f4e5ea3a6/packages/edit-post/src/components/sidebar/settings-sidebar/index.js

const sidebarActiveByDefault = Platform.select({
  web: true,
  native: false,
});

type Props = ComponentProps<typeof ComplementaryArea>;

export function Sidebar(props: Props): JSX.Element {
  const { keyboardShortcut, sidebarKey, showIconLabels } = useSelect(
    (select) => ({
      keyboardShortcut: select(
        keyboardShortcutsStore,
      ).getShortcutRepresentation('core/edit-post/toggle-sidebar'),
      sidebarKey:
        select(interfaceStore).getActiveComplementaryArea(storeName) ??
        emailSidebarKey,
      showIconLabels: false,
    }),
    [],
  );

  return (
    <ComplementaryArea
      identifier={sidebarKey}
      header={<Header sidebarKey={sidebarKey} />}
      closeLabel={__('Close settings')}
      headerClassName="edit-post-sidebar__panel-tabs"
      title={__('Settings')}
      icon={cog}
      className="edit-post-sidebar"
      panelClassName="edit-post-sidebar"
      smallScreenTitle={__('(no title)')}
      scope={storeName}
      toggleShortcut={keyboardShortcut}
      isActiveByDefault={sidebarActiveByDefault}
      showIconLabels={showIconLabels}
      {...props}
    >
      {sidebarKey === blockSidebarKey && <BlockSidebar />}
      {sidebarKey === emailSidebarKey && <EmailSidebar />}
    </ComplementaryArea>
  );
}
