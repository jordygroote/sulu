// @flow
import type {Node} from 'react';
import type {IObservableValue} from 'mobx';
import type {Button, Dropdown, Select, Toggler} from '../../components/Toolbar/types';

export type {Button, Dropdown, Select, Toggler};

export type ButtonItemConfig = {|
    ...Button,
    type: 'button',
|};

export type DropdownItemConfig = {|
    ...Dropdown,
    type: 'dropdown',
|};

export type SelectItemConfig<T> = {|
    ...Select<T>,
    type: 'select',
|};

export type TogglerItemConfig = {|
    ...Toggler,
    type: 'toggler',
|};
export type ToolbarItemConfig<T> = ButtonItemConfig | DropdownItemConfig | SelectItemConfig<T> | TogglerItemConfig;

export type ToolbarProps = {
    navigationOpen?: boolean,
    onNavigationButtonClick?: () => void,
    storeKey?: string,
};

export type ToolbarConfig = {
    backButton?: Button,
    disableAll?: boolean,
    errors?: Array<string>,
    icons?: Array<Node>,
    items?: Array<ToolbarItemConfig<*>>,
    locale?: Select<string>,
    showSuccess?: IObservableValue<boolean>,
    warnings?: Array<string>,
};
