import { MailPoet } from 'mailpoet';
import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl } from '@wordpress/components';
import { assocPath, compose, cond, identity, isEqual, sortBy } from 'lodash/fp';
import { withBoundary } from 'common';
import { Selection } from '../../selection';
import { storeName } from '../../../../store';

type Props = {
  settingsPlacementKey: string;
};

function PlacementSettings({ settingsPlacementKey }: Props): JSX.Element {
  const formSettings = useSelect(
    (select) => select(storeName).getFormSettings(),
    [],
  );
  const tags = useSelect(
    (select) =>
      sortBy(
        'name',
        select(storeName)
          .getAllWPTags()
          .concat(select(storeName).getAllWooCommerceTags()),
      ),
    [],
  );
  const categories = useSelect(
    (select) =>
      sortBy(
        'name',
        select(storeName)
          .getAllWPCategories()
          .concat(select(storeName).getAllWooCommerceCategories()),
      ),
    [],
  );
  const pages = useSelect((select) => select(storeName).getAllWPPages(), []);
  const posts = useSelect(
    (select) =>
      sortBy(
        'name',
        select(storeName)
          .getAllWPPosts()
          .concat(select(storeName).getAllWooCommerceProducts()),
      ),
    [],
  );
  const isPreviewShown = useSelect(
    (select) => select(storeName).getIsPreviewShown(),
    [],
  );
  const { changeFormSettings } = useDispatch(storeName);

  let prefix = 'no-preview';
  if (isPreviewShown) {
    prefix = 'preview';
  }

  const placementSupportsArchivePages = settingsPlacementKey !== 'belowPosts';

  return (
    <>
      <ToggleControl
        label={MailPoet.I18n.t('placeFormOnHomepage')}
        checked={formSettings.formPlacement[settingsPlacementKey].homepage}
        onChange={(newValue): void => {
          compose([
            changeFormSettings,
            assocPath(
              `formPlacement.${settingsPlacementKey}.homepage`,
              newValue,
            ),
          ])(formSettings);
        }}
      />
      <ToggleControl
        label={MailPoet.I18n.t('placeFormOnAllPages')}
        checked={formSettings.formPlacement[settingsPlacementKey].pages.all}
        onChange={(newValue): void => {
          compose([
            changeFormSettings,
            assocPath(
              `formPlacement.${settingsPlacementKey}.pages.all`,
              newValue,
            ),
            cond([
              [
                // condition, if the predicate function is true the next compose is run
                (): boolean => newValue,
                compose([
                  assocPath(
                    `formPlacement.${settingsPlacementKey}.pages.selected`,
                    [],
                  ), // if enabled clear selected pages
                  assocPath(
                    `formPlacement.${settingsPlacementKey}.categories`,
                    [],
                  ), // if enabled clear selected categories
                  assocPath(`formPlacement.${settingsPlacementKey}.tags`, []), // if enabled clear selected tags
                ]),
              ],
              [(): boolean => !newValue, identity], // if disabled do nothing
            ]),
          ])(formSettings);
        }}
      />
      <div data-automation-id="form-placement-select-page">
        <div className="form-editor-placement-selection">
          <Selection
            dropDownParent={
              isPreviewShown ? '.mailpoet-modal-content' : undefined
            }
            item={{
              id: `${prefix}${formSettings.formPlacement[
                settingsPlacementKey
              ].pages.selected.join()}`,
            }}
            onValueChange={(e): void => {
              const selected =
                formSettings.formPlacement[settingsPlacementKey].pages.selected;
              if (isEqual(selected, e.target.value)) {
                return;
              }
              compose([
                changeFormSettings,
                assocPath(
                  `formPlacement.${settingsPlacementKey}.pages.selected`,
                  e.target.value,
                ),
                cond([
                  [
                    // only disable "All pages" toggle if not empty
                    (): boolean => !!e.target.value.length,
                    assocPath(
                      `formPlacement.${settingsPlacementKey}.pages.all`,
                      false,
                    ), // disable all if some pages are selected
                  ],
                  [(): boolean => !e.target.value.length, identity],
                ]),
              ])(formSettings);
            }}
            field={{
              id: `${prefix}pages`,
              name: 'pages',
              values: pages,
              multiple: true,
              placeholder: MailPoet.I18n.t('selectPage'),
              getLabel: (page): void => page.name,
              selected: (): void =>
                formSettings.formPlacement[settingsPlacementKey].pages.selected,
            }}
          />
        </div>
      </div>
      <ToggleControl
        label={MailPoet.I18n.t('placeFormOnAllPosts')}
        checked={formSettings.formPlacement[settingsPlacementKey].posts.all}
        onChange={(newValue): void => {
          compose([
            changeFormSettings,
            assocPath(
              `formPlacement.${settingsPlacementKey}.posts.all`,
              newValue,
            ),
            cond([
              [
                (): boolean => newValue,
                compose([
                  assocPath(
                    `formPlacement.${settingsPlacementKey}.posts.selected`,
                    [],
                  ), // if enabled clear selected pages
                  assocPath(
                    `formPlacement.${settingsPlacementKey}.categories`,
                    [],
                  ), // if enabled clear selected categories
                  assocPath(`formPlacement.${settingsPlacementKey}.tags`, []), // if enabled clear selected tags
                ]),
              ],
              [(): boolean => !newValue, identity], // if disabled do nothing
            ]),
          ])(formSettings);
        }}
      />
      <div className="form-editor-placement-selection">
        <Selection
          dropDownParent={
            isPreviewShown ? '.mailpoet-modal-content' : undefined
          }
          item={{
            id: `${prefix}${formSettings.formPlacement[
              settingsPlacementKey
            ].posts.selected.join()}`,
          }}
          onValueChange={(e): void => {
            const selected =
              formSettings.formPlacement[settingsPlacementKey].posts.selected;
            if (isEqual(selected, e.target.value)) {
              return;
            }
            compose([
              changeFormSettings,
              assocPath(
                `formPlacement.${settingsPlacementKey}.posts.selected`,
                e.target.value,
              ),
              cond([
                [
                  // only disable "All pages" toggle if not empty
                  (): boolean => !!e.target.value.length,
                  assocPath(
                    `formPlacement.${settingsPlacementKey}.posts.all`,
                    false,
                  ), // disable all if some pages are selected
                ],
                [(): boolean => !e.target.value.length, identity],
              ]),
            ])(formSettings);
          }}
          field={{
            id: `${prefix}posts`,
            name: 'posts',
            values: posts,
            multiple: true,
            placeholder: MailPoet.I18n.t('selectPage'),
            getLabel: (page): string => page.name,
            selected: (): Array<string> =>
              formSettings.formPlacement[settingsPlacementKey].posts.selected,
          }}
        />
      </div>
      <div>
        <p className="form-editor-sidebar-heading">
          {MailPoet.I18n.t('displayOnCategories')}
        </p>
        <div className="form-editor-placement-selection">
          <Selection
            dropDownParent={
              isPreviewShown ? '.mailpoet-modal-content' : undefined
            }
            item={{
              id: `${prefix}${formSettings.formPlacement[
                settingsPlacementKey
              ].categories.join()}`,
            }}
            onValueChange={(e): void => {
              const selected =
                formSettings.formPlacement[settingsPlacementKey].categories;
              if (isEqual(selected, e.target.value)) {
                return;
              }
              compose([
                changeFormSettings,
                assocPath(
                  `formPlacement.${settingsPlacementKey}.categories`,
                  e.target.value,
                ),
                cond([
                  [
                    // only disable "All pages" toggle if not empty
                    (): boolean => !!e.target.value.length,
                    compose([
                      assocPath(
                        `formPlacement.${settingsPlacementKey}.pages.all`,
                        false,
                      ),
                      assocPath(
                        `formPlacement.${settingsPlacementKey}.posts.all`,
                        false,
                      ), // disable all if some posts are selected
                    ]),
                  ],
                  [(): boolean => !e.target.value.length, identity],
                ]),
              ])(formSettings);
            }}
            field={{
              id: `${prefix}categories`,
              name: 'categories',
              values: categories,
              multiple: true,
              placeholder: MailPoet.I18n.t('selectPage'),
              getLabel: (category): string => category.name,
              selected: (): Array<string> =>
                formSettings.formPlacement[settingsPlacementKey].categories,
            }}
          />
        </div>
      </div>
      <div>
        <p className="form-editor-sidebar-heading">
          {MailPoet.I18n.t('displayOnTags')}
        </p>
        <div className="form-editor-placement-selection">
          <Selection
            dropDownParent={
              isPreviewShown ? '.mailpoet-modal-content' : undefined
            }
            item={{
              id: `${prefix}${formSettings.formPlacement[
                settingsPlacementKey
              ].tags.join()}`,
            }}
            onValueChange={(e): void => {
              const selected =
                formSettings.formPlacement[settingsPlacementKey].tags;
              if (isEqual(selected, e.target.value)) {
                return;
              }
              compose([
                changeFormSettings,
                assocPath(
                  `formPlacement.${settingsPlacementKey}.tags`,
                  e.target.value,
                ),
                cond([
                  [
                    // only disable "All pages" toggle if not empty
                    (): boolean => !!e.target.value.length,
                    compose([
                      assocPath(
                        `formPlacement.${settingsPlacementKey}.pages.all`,
                        false,
                      ),
                      assocPath(
                        `formPlacement.${settingsPlacementKey}.posts.all`,
                        false,
                      ), // disable all if some posts are selected
                    ]),
                  ],
                  [(): boolean => !e.target.value.length, identity],
                ]),
              ])(formSettings);
            }}
            field={{
              id: `${prefix}tags`,
              name: 'tags',
              values: tags,
              multiple: true,
              placeholder: MailPoet.I18n.t('selectPage'),
              getLabel: (tag): string => tag.name,
              selected: (): Array<string> =>
                formSettings.formPlacement[settingsPlacementKey].tags,
            }}
          />
        </div>
      </div>
      {placementSupportsArchivePages && (
        <>
          <ToggleControl
            label={MailPoet.I18n.t('displayOnAllTagArchives')}
            checked={
              formSettings.formPlacement[settingsPlacementKey].tagArchives.all
            }
            onChange={(newValue): void => {
              compose([
                changeFormSettings,
                assocPath(
                  `formPlacement.${settingsPlacementKey}.tagArchives.all`,
                  newValue,
                ),
                cond([
                  [
                    // condition, if the predicate function is true the next compose is run
                    (): boolean => newValue,
                    compose([
                      assocPath(
                        `formPlacement.${settingsPlacementKey}.tagArchives.selected`,
                        [],
                      ), // if enabled clear selected tags
                    ]),
                  ],
                  [(): boolean => !newValue, identity], // if disabled do nothing
                ]),
              ])(formSettings);
            }}
          />
          <div data-automation-id="form-placement-select-archive-tags">
            <div className="form-editor-placement-selection">
              <Selection
                dropDownParent={
                  isPreviewShown ? '.mailpoet-modal-content' : undefined
                }
                item={{
                  id: `${prefix}${formSettings.formPlacement[
                    settingsPlacementKey
                  ].tagArchives.selected.join()}`,
                }}
                onValueChange={(e): void => {
                  const selected =
                    formSettings.formPlacement[settingsPlacementKey].tagArchives
                      .selected;
                  if (isEqual(selected, e.target.value)) {
                    return;
                  }
                  compose([
                    changeFormSettings,
                    assocPath(
                      `formPlacement.${settingsPlacementKey}.tagArchives.selected`,
                      e.target.value,
                    ),
                    cond([
                      [
                        // only disable "All pages" toggle if not empty
                        (): boolean => !!e.target.value.length,
                        assocPath(
                          `formPlacement.${settingsPlacementKey}.tagArchives.all`,
                          false,
                        ), // disable all if some pages are selected
                      ],
                      [(): boolean => !e.target.value.length, identity],
                    ]),
                  ])(formSettings);
                }}
                field={{
                  id: `${prefix}tagArchives`,
                  name: 'tagArchives',
                  values: tags,
                  multiple: true,
                  placeholder: MailPoet.I18n.t('selectSpecificArchiveTags'),
                  getLabel: (tag): void => tag.name,
                  selected: (): void =>
                    formSettings.formPlacement[settingsPlacementKey].tagArchives
                      .selected,
                }}
              />
            </div>
          </div>
          <ToggleControl
            label={MailPoet.I18n.t('displayOnAllCategoryArchives')}
            checked={
              formSettings.formPlacement[settingsPlacementKey].categoryArchives
                .all
            }
            onChange={(newValue): void => {
              compose([
                changeFormSettings,
                assocPath(
                  `formPlacement.${settingsPlacementKey}.categoryArchives.all`,
                  newValue,
                ),
                cond([
                  [
                    // condition, if the predicate function is true the next compose is run
                    (): boolean => newValue,
                    compose([
                      assocPath(
                        `formPlacement.${settingsPlacementKey}.categoryArchives.selected`,
                        [],
                      ), // if enabled clear selected categories
                    ]),
                  ],
                  [(): boolean => !newValue, identity], // if disabled do nothing
                ]),
              ])(formSettings);
            }}
          />
          <div data-automation-id="form-placement-select-archive-categories">
            <div className="form-editor-placement-selection">
              <Selection
                dropDownParent={
                  isPreviewShown ? '.mailpoet-modal-content' : undefined
                }
                item={{
                  id: `${prefix}${formSettings.formPlacement[
                    settingsPlacementKey
                  ].categoryArchives.selected.join()}`,
                }}
                onValueChange={(e): void => {
                  const selected =
                    formSettings.formPlacement[settingsPlacementKey]
                      .categoryArchives.selected;
                  if (isEqual(selected, e.target.value)) {
                    return;
                  }
                  compose([
                    changeFormSettings,
                    assocPath(
                      `formPlacement.${settingsPlacementKey}.categoryArchives.selected`,
                      e.target.value,
                    ),
                    cond([
                      [
                        // only disable "All pages" toggle if not empty
                        (): boolean => !!e.target.value.length,
                        assocPath(
                          `formPlacement.${settingsPlacementKey}.categoryArchives.all`,
                          false,
                        ), // disable all if some pages are selected
                      ],
                      [(): boolean => !e.target.value.length, identity],
                    ]),
                  ])(formSettings);
                }}
                field={{
                  id: `${prefix}categoryArchives`,
                  name: 'categoryArchives',
                  values: categories,
                  multiple: true,
                  placeholder: MailPoet.I18n.t(
                    'selectSpecificArchiveCategories',
                  ),
                  getLabel: (tag): void => tag.name,
                  selected: (): void =>
                    formSettings.formPlacement[settingsPlacementKey]
                      .categoryArchives.selected,
                }}
              />
            </div>
          </div>
        </>
      )}
    </>
  );
}

PlacementSettings.displayName = 'FormEditorPlacementSettings';
const PlacementSettingsWithBoundary = withBoundary(PlacementSettings);
export { PlacementSettingsWithBoundary as PlacementSettings };
