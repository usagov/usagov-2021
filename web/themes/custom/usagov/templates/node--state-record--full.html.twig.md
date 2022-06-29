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
      <p>{{content["field_phone_number"][0]}}</p>
    </td>
  </tr>

  <tr>
    <td>
      <h3>Congress Members</h3>
      <p>{{content["field_phone_number"][0]}}</p>
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

</table>
