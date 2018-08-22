/**
 * @file
 * Sets up the Campaign App.
 */

// Create module and configure routing and translations.
angular.module('campaignApp').config([
    '$routeProvider', '$translateProvider', function ($routeProvider, $translateProvider) {
        'use strict';

        var appUrl = 'bundles/os2displaycampaign/apps/campaignApp/';

        // Set up translations.
        $translateProvider
            .useSanitizeValueStrategy('escape')
            .useStaticFilesLoader({
                prefix: appUrl + 'translations/locale-',
                suffix: '.json'
            })
            .preferredLanguage('da')
            .fallbackLanguage('da')
            .forceAsyncReload(true);

        // Register routes
        $routeProvider
            .when('/campaign', {
                controller: 'CampaignOverviewController',
                templateUrl: appUrl + 'campaign-overview/campaign-overview.html' + '?' + window.config.version
            })
            .when('/campaign/create', {
                controller: 'CampaignController',
                templateUrl: appUrl + 'campaign/campaign.html' + '?' + window.config.version
            })
            .when('/campaign/:id', {
                controller: 'CampaignController',
                templateUrl: appUrl + 'campaign/campaign.html' + '?' + window.config.version
            });
    }
]);

// Setup the app.
// Register menu items.
angular.module('campaignApp').service('campaignAppSetup', [
    'busService', 'userService', '$translate', '$filter',
    function (busService, userService, $translate, $filter) {
        'use strict';

        // Register listener for requests for Main Menu items
        busService.$on('menuApp.requestMainMenuItems', function requestMainMenuItems (event, args) {
            if (userService.hasRole('ROLE_CAMPAIGN_ADMIN')) {
                busService.$emit('menuApp.returnMainMenuItems', [
                    {
                        title: $translate.instant('menu.campaign'),
                        route: '/#/campaign',
                        activeFilter: '/campaign',
                        icon: 'picture_in_picture',
                        weight: 6
                    }
                ]);
            }
        });

        busService.$on('menuApp.requestHamburgerMenuItems', function requestHamburgerMenuItems (event, args) {
            busService.$emit('menuApp.returnHamburgerMenuItems', [
                    {
                        title: $translate.instant('menu.campaign'),
                        weight: 5,
                        items: [
                            {
                                title: 'Oversigt',
                                route: '/#/campaign',
                                activeFilter: '/campaign',
                                weight: 1
                            },
                            {
                                title: 'Opret kanal',
                                route: '/#/campaign/create',
                                activeFilter: '/campaign/create',
                                weight: 2
                            }
                        ]
                    }
                ]
            );
        });

        // Listen for sub menu requests
        busService.$on('menuApp.requestSubMenuItems', function (event, data) {
            var items = [];

            if (userService.hasRole('ROLE_CAMPAIGN_ADMIN')) {
                items.push({
                    title: $translate.instant('menu.campaign_create'),
                    path: '#/campaign/create',
                    activeFilter: '/campaign/create',
                    group: 'left',
                    weight: 1
                });
            }

            busService.$emit('menuApp.returnSubMenuItems', [
                {
                    mainMenuItem: 'campaign',
                    items: items
                }
            ]);
        });

        busService.$on('itkHeader.list.element.requestItems', function (event, data) {
            if (data.type === 'screen') {
                var apiData = data.entity.api_data;

                if (apiData && apiData.active_campaigns && apiData.active_campaigns.length > 0) {
                    var message = '<p>' + $translate.instant('messages.active_campaign_exists') + '</p>';

                    var iconSource = 'bundles/os2displaycampaign/assets/icons/calendar.png';

                    var html =
                        '<div tooltips tooltip-template="' + message + '" tooltip-side="top">' +
                        '  <div class="campaign-info--icon-container"><img class="campaign-info--icon" src="' + iconSource + '" title=""></div>' +
                        '</div>';

                    busService.$emit(data.returnEvent, {
                        html: html,
                        type: 'warning'
                    });
                }
            }
        });

        busService.$on('itkHeader.entity.requestItems', function (event, data) {
            if (data.type === 'screen') {
                var apiData = data.entity.api_data;

                if (apiData && apiData.active_campaigns && apiData.active_campaigns.length > 0) {
                    var message = apiData.active_campaigns.reduce(function (sum, campaign) {
                        return sum + '<p>' + $translate.instant('messages.active_campaign') + $filter('date')(campaign.schedule_from, 'd/M/yyyy H:mm') + ' - ' + $filter('date')(campaign.schedule_to, 'd/M/yyyy H:mm') + ' (<a href="/#/campaign/' + campaign.id + '">' + campaign.title + '</a>)</p>';
                    }, '');

                    var html =
                        '<div class="message campaign-info--message">' +
                        '  <div class="message--inner is-info">' +
                        '    <div class="message--content">' +
                        message +
                        '    </div>' +
                        '  </div>' +
                        '</div>';

                    busService.$emit(data.returnEvent, {
                        html: html,
                        type: 'warning'
                    });
                }
            }
        });
    }
]);

// Start the service.
angular.module('campaignApp').run(['campaignAppSetup', angular.noop]);
