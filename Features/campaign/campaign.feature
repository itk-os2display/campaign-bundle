@campaign
Feature: campaign
  I need to be able to push data to screens

  Background:
    When the following channels exist:
      | title                    | created_at | modified_at |
      | Channel 1                | 0          | 0           |
      | Channel 2                | 1          | 1           |
      | Hostile Takeover Channel | 2          | 2           |
    And the following screens exist:
      | title    | channel | created_at | modified_at |
      | Screen 1 | 1       | 0          | 0           |
      | Screen 2 |         | 0          | 0           |
    And the following users exist:
      | username | password | roles                                      | groups |
      | user     | user     | ROLE_USER, ROLE_CAMPAIGN_ADMIN, ROLE_ADMIN |        |
    And I sign in with username "user" and password "user"
    And I add "content-type" header equal to "application/json"

  @createSchema
  Scenario: Create schema

  Scenario: Assert that channel 1 is pushed to screen 1 and not pushed the second time since there are no changes.
    When I call pushToScreens
    And I get all the utility service curl calls with prefix middleware
    Then curl calls should equal:
      | url                                            | method | data                                                                                                                                                    | prefix     |
      | https://middleware.os2display.vm/api/channel/1 | POST   | {"title":"Channel 1","data":{"id":1,"slides":[],"schedule_repeat_days":[]},"regions":[{"screen":1,"region":1}],"screens":[1],"schedule_repeat_days":[]} | middleware |
    And channel 1 should be pushed to screen 1
    And channel 2 should not be pushed to screen 1
    And I clear utility service
    And I call pushToScreens
    And I get all the utility service curl calls with prefix middleware
    Then curl calls should equal:
      | url | method | data | prefix |

  Scenario: Assert that campaign will take over other channels
    When I clear all channels
    And I clear utility service
    And I send a "POST" request to "/api/campaign" with body:
    """
    {
      "title": "The first campaign",
      "schedule_from": "2001-01-01",
      "schedule_to": "2041-01-31",
      "groups": [],
      "channels": [3],
      "screen_groups": [],
      "screens": [1]
    }
    """
    And the response status code should be 201
    And I call pushToScreens
#    And I print all the utility service curl calls
    And I get all the utility service curl calls with prefix middleware
    Then channel 1 should not be pushed to screen 1
    And channel 2 should not be pushed to screen 1
    And channel 3 should be pushed to screen 1

  # Assumes campaign created in above scenario
  Scenario: Assert that campaign will only take over region 1
    When I clear all channels
    And I clear utility service
    And I add channel screen region with channel 1 screen 1 region 2
    And I add channel screen region with channel 2 screen 1 region 3
    And I add channel screen region with channel 3 screen 1 region 4
    And I call pushToScreens
    And I get all the utility service curl calls with prefix middleware
    Then channel 1 should be pushed to screen 1
    And channel 2 should be pushed to screen 1
    And channel 3 should be pushed to screen 1
    And channel 1 should not be pushed to screen 1 region 1
    And channel 1 should be pushed to screen 1 region 2
    And channel 2 should be pushed to screen 1 region 3
    And channel 3 should be pushed to screen 1 region 4

  # Assumes above scenario has run
  Scenario: Assert no push after already pushing content
    When I clear utility service
    And I call pushToScreens
    And I get all the utility service curl calls with prefix middleware
    Then curl calls should equal:
      | url | method | data | prefix |

  Scenario: Check that campaign with screen group is applied to the given channel
    When I send a "POST" request to "/api/group" with body:
      """
      {
        "title": "The first group"
      }
      """
    And the response status code should be 201
    And I send a "POST" request to "/api/channel" with body:
      """
      {
        "id": null,
        "title": "Channel 4",
        "slides": [],
        "screens": [],
        "groups": [],
        "orientation": "",
        "created_at": 1507280950,
        "sharing_indexes": [],
        "schedule_repeat_days": []
      }
      """
    And the response status code should be 200
    And I send a "POST" request to "/api/screen" with body:
      """
      {
        "id": null,
        "title": "Screen in group",
        "description": "Description of The first screen",
        "groups": [1]
      }
      """
    And the response status code should be 200

    # Reset user and content type.
    And I sign in with username "user" and password "user"
    And I add "content-type" header equal to "application/json"

    And I send a "POST" request to "/api/screen" with body:
      """
      {
        "id": null,
        "title": "Screen 2 in group",
        "description": "Description",
        "groups": [1]
      }
      """
    And the response status code should be 200

    # Reset user and content type.
    And I sign in with username "user" and password "user"
    And I add "content-type" header equal to "application/json"

    And the response status code should be 200
    And I send a "POST" request to "/api/campaign" with body:
    """
    {
      "title": "Second campaign",
      "schedule_from": "2002-01-01",
      "schedule_to": "2039-01-31",
      "groups": [],
      "channels": [4],
      "screen_groups": [1],
      "screens": []
    }
    """
    And the response status code should be 201

    And I clear utility service
    And I call pushToScreens

    And channel 4 should not be pushed to screen 1
    And channel 4 should not be pushed to screen 2
    And channel 4 should be pushed to screen 3
    And channel 4 should be pushed to screen 4

  Scenario: When I change the campaign to and old date, it should be removed from screens
    When I send a "PUT" request to "/api/campaign/2" with body:
    """
    {
      "id": 2,
      "schedule_to": "2003-01-31"
    }
    """
    And the response status code should be 200
    And I clear utility service
    And I call pushToScreens
    And channel 4 should be deleted from middleware

    # Reset user and content type.
    And I sign in with username "user" and password "user"
    And I add "content-type" header equal to "application/json"

    And I send a "PUT" request to "/api/campaign/2" with body:
    """
    {
      "id": 2,
      "schedule_to": "2033-01-31"
    }
    """
    And I clear utility service
    And I call pushToScreens

    Then channel 4 should not be pushed to screen 1
    And channel 4 should not be pushed to screen 2
    And channel 4 should be pushed to screen 3
    And channel 4 should be pushed to screen 4

  Scenario: When a shared channel is added to screen it is still overridden by a campaign
    When I create a shared channel with content uniqueId "test1" index "index1" screens "[1]"
    And I clear utility service
    And I call pushToScreens
    And I print all the utility service curl calls
    And I get all the utility service curl calls with prefix middleware

  @dropSchema
  Scenario: Drop schema
