<table class="usagov-sd-table">

  <tr>
    <td>
      <h3>State Government Website/h3>
      <a href=content["field_official_website_primary"][0]>{{content["field_official_website_primary"][0]}}</a>
    </td>
  </tr>

  <tr>
    <td>
      <h3>Governor</h3>
      <a href=content["field_governor_website"][0]>{{content["field_governor_website"][0]}}</a>
      <a href=content["field_governor_contact"][0]>{{content["field_governor_contact"][0]}}</a>
      <h3>Phone</h3>
      <p>{{content["field_phone_number"][0]}}</p>
      <h3>Main Address</h3>
      {% for key, obj in content["field_street_1"] %}
        {% if key matches '/^\\d+$/' %}
          <p>{{content["field_street_1"][0]}}</p>
        {% endif %}
      {% endfor %}
      {% for key, obj in content["field_street_2"] %}
        {% if key matches '/^\\d+$/' %}
          <p>{{content["field_street_2"][0]}}</p>
        {% endif %}
      {% endfor %}
      {% for key, obj in content["field_street_3"] %}
        {% if key matches '/^\\d+$/' %}
          <p>{{content["field_street_3"][0]}}</p>
        {% endif %}
      {% endfor %}
    </td>
  </tr>

  <tr>
    <td>
      <h3>Congress Members</h3>
      <a href=content["field_congress_member_contact"][0]>
      Find the names and contact information for your elected officials
      </a>
    </td>
  </tr>

  <tr>
    <td>
      <h3>Find an Office Near You</h3>
        <div>
          {{content["field_offices_near_you"][0]}}
        </div>
    </td>
  </tr>

  <tr>
    <td>
      <h3>State Agencies</h3>
       <p><a href=/>{{content["field_state_agency"][0]}}</a></p>
        {% for key, obj in content["field_state_agency_additional"] %}
        {% if key matches '/^\\d+$/' %}
            <p><a href=/>{{content["field_state_agency_additional"][key]}}</a></p>
        {% endif %}
        {% endfor %}
    </td>
  </tr>

</table>
