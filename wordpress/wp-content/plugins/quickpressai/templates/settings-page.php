<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap">
    <h1>QuickPress AI v<?php echo esc_html(QUICKPRESS_AI_VERSION); ?></h1>

    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="#" class="nav-tab nav-tab-active" data-tab="settings-tab">Settings</a>
        <?php if (!empty(trim($api_key))) : ?>
        <a href="#" class="nav-tab" data-tab="keyword-tab">Keyword Creator</a>
        <?php endif; ?>
    </h2>

    <!-- Settings Tab -->
    <div id="settings-tab" class="tab-content active">
        <?php settings_errors('quickpress_ai_settings'); ?>
        <form method="post" action="options.php">
            <?php settings_fields('quickpress_ai_settings'); ?>
            <table class="form-table">
                <tr valign="middle">
                    <th style="vertical-align: middle;" scope="row">
                        Venice AI API Key
                        <br /><a href="<?php echo esc_url(QUICKPRESS_AI_WEBSITE_BASE_URL); ?>/docs/" target="_blank">Installation Docs & FAQs</a>
                    </th>
                    <td>
                        <?php if (empty(trim($api_key))) : ?>
                            <input type="password" name="quickpress_ai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                        <?php else : ?>
                            <input type="password" value="Encrypted Venice AI API Key" class="regular-text" disabled />
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty(trim($api_key))) : ?>
                            <p>Your Venice AI API key is encrypted and securely saved.<br />
                            <a href="#" onclick="event.preventDefault(); if(confirm('Are you sure you want to reset the Venice AI API key? This action cannot be undone.')) { document.getElementById('api-key-reset-form').submit(); }">
                            Click here</a> to reset it.</p>
                        <?php endif; ?>
                    </td>
                </tr>

            <?php if (!empty(trim($api_key))) : ?>
                <tr valign="top">
                    <th scope="row">AI Model</th>
                    <td>
                        <?php if (is_wp_error($models)) : ?>
                            <p style="color: red;"><?php echo esc_html($models->get_error_message()); ?></p>
                        <?php elseif (is_null($models)) : ?>
                            <p>Please add an API key to fetch models.</p>
                        <?php elseif (empty($models)) : ?>
                            <p>No text models available.</p>
                        <?php else : ?>
                            <select name="quickpress_ai_selected_model">
                                <option value="">Select one:</option>
                                <?php foreach ($models as $model) : ?>
                                    <?php
                                    $model_id = isset($model['id']) ? esc_attr($model['id']) : 'Unknown Model';
                                    $traits = isset($model['model_spec']['traits']) && is_array($model['model_spec']['traits'])
                                        ? array_filter($model['model_spec']['traits'], function ($trait) {
                                            return !is_null($trait) && is_string($trait);
                                        })
                                        : [];
                                    $traits_display = !empty($traits)
                                        ? esc_html(str_replace('_', ' ', implode(', ', $traits)))
                                        : 'No capabilities or traits specified';
                                    ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected(esc_attr($selected_model), $model_id); ?>>
                                        <?php echo esc_html($model_id . ' (' . $traits_display . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($selected_model)) : ?>
                                <p>Saved model: <?php echo esc_html($selected_model); ?></p> <!-- Escape selected model for safe usage -->
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <p><strong>Required</strong> for content generation. Select a model based on your requirements. Each model has specific capabilities and traits.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Writing Assistant Configuration</th>
                    <td>
                        <textarea name="quickpress_ai_system_prompt" rows="5" class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
                    </td>
                    <td>
                        <p>Describe your writing assistant in detail. You could start with "You are a helpful writing assistant with expert SEO skills" and expand from there.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">System Prompt</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="quickpress_ai_system_prompt_option" value="true" <?php checked($system_prompt_option, 'true'); ?> />
                                Add assistant configuration to Venice AI's system prompt.
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="quickpress_ai_system_prompt_option" value="false" <?php checked($system_prompt_option, 'false'); ?> />
                                Use my writing assistant configuration only.
                            </label>
                        </fieldset>
                    </td>
                    <td>
                        <p>Venice AI provides default system prompts designed to ensure uncensored and natural model responses.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Title Prompt Template</th>
                    <td>
                        <textarea name="quickpress_ai_title_prompt_template" rows="5" class="large-text"><?php echo esc_textarea(get_option('quickpress_ai_title_prompt_template', '')); ?></textarea>
                    </td>
                    <td>
                        <p>Save a default set of instructions that you can load in the WordPress editor to guide the AI when writing page/post titles.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Page/Post Content Prompt Template</th>
                    <td>
                        <textarea name="quickpress_ai_content_prompt_template" rows="5" class="large-text"><?php echo esc_textarea(get_option('quickpress_ai_content_prompt_template', '')); ?></textarea>
                    </td>
                    <td>
                        <p>Save a default set of instructions that you can load in the WordPress editor to guide the AI when writing page/post content.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Excerpt Prompt Template</th>
                    <td>
                        <textarea name="quickpress_ai_excerpt_prompt_template" rows="5" class="large-text"><?php echo esc_textarea(get_option('quickpress_ai_excerpt_prompt_template', '')); ?></textarea>
                    </td>
                    <td>
                        <p>Save a default set of instructions that you can load in the WordPress editor to guide the AI when generating page/post excerpts.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th style="vertical-align: middle;" scope="row">Temperature</th>
                    <td>
                        <select name="quickpress_ai_temperature">
                            <?php
                            $current_value = get_option('quickpress_ai_temperature', '1.0');
                            $options = [
                                '0.1' => 'Precise (Least Random)',
                                '0.3' => 'Focused',
                                '0.5' => 'Balanced',
                                '0.7' => 'Creative',
                                '1.0' => 'Very Creative (Default)',
                                '1.5' => 'Highly Unpredictable',
                                '1.9' => 'Most Random (Near Maximum)',
                            ];
                            foreach ($options as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($current_value, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </td>
                    <td>
                        <p>Controls the AI's randomness. Lower values produce more predictable content. Higher values produce more creative and varied output.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th style="vertical-align: middle;" scope="row">Frequency Penalty</th>
                    <td>
                        <select name="quickpress_ai_frequency_penalty">
                            <?php
                            $current_value = get_option('quickpress_ai_frequency_penalty', '0.0');
                            $options = [
                                '-1.5' => 'Encourage repetition significantly',
                                '-1.0' => 'Increase repetition likelihood',
                                '-0.5' => 'Slight repetition',
                                '0.0'  => 'No impact on repetition',
                                '0.3'  => 'Slightly discourage repetition',
                                '0.5'  => 'Moderately reduce repeated phrases',
                                '0.7'  => 'Further decrease likelihood of repetition',
                                '1.0'  => 'Strongly discourage repeated phrases',
                                '1.5'  => 'Maximum reduction of repetition',
                            ];
                            foreach ($options as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($current_value, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </td>
                    <td>
                        <p>Adjusts the likelihood of repeated phrases. Higher values penalize repeated words or phrases, encouraging more varied output.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th style="vertical-align: middle;" scope="row">Presence Penalty</th>
                    <td>
                        <select name="quickpress_ai_presence_penalty">
                            <?php
                            $current_value = get_option('quickpress_ai_presence_penalty', '0.0');
                            $options = [
                                '-1.5' => 'Highly favor staying on the same topic',
                                '-1.0' => 'Increase likelihood of maintaining topic focus',
                                '-0.5' => 'Slightly reduce topic changes',
                                '0.0'  => 'No impact on topic diversity',
                                '0.3'  => 'Slightly encourage shifting topics',
                                '0.5'  => 'Moderately increase topic changes',
                                '0.7'  => 'Strongly favor shifting topics',
                                '1.0'  => 'Prioritize discussing new topics',
                                '1.5'  => 'Maximize topic diversity',
                            ];
                            foreach ($options as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($current_value, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </td>
                    <td>
                        <p>Encourages the model to discuss new topics. Higher values discourage repetition, leading to more diverse content.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Generate Content Timeout (seconds)</th>
                    <td>
                        <input type="number" name="quickpress_ai_generate_timeout" value="<?php echo esc_attr(get_option('quickpress_ai_generate_timeout', '120')); ?>" class="small-text" />
                    </td>
                    <td>
                        <p>Specify the time to wait for content generation. The default is 120 seconds, and the maximum is 180 seconds.</p>
                    </td>
                </tr>
            <?php endif; ?>
            </table>
        <?php submit_button('Save'); ?>
    </form>

  </div>
<?php if (!empty(trim($api_key))) : ?>
  <div id="keyword-tab" class="tab-content">
      <form method="post" action="options.php">
      <?php settings_fields('quickpress_ai_serpstack_settings'); ?>
      <?php if (isset($_GET['message']) && $_GET['message'] === 'api_key_reset') : ?>
          <div class="updated notice is-dismissible">
              <p><?php _e('serpstack API Key has been reset.', 'quickpress-ai'); ?></p>
          </div>
      <?php endif; ?>
      <table class="form-table">
        <tr valign="middle">
            <th style="vertical-align: top;" scope="row">
                serpstack API Key
                <br /><a href="<?php echo esc_url(QUICKPRESS_AI_WEBSITE_BASE_URL); ?>/docs/#KeywordCreator" target="_blank">Instructions</a>
            </th>
            <td style="vertical-align: middle">
                <?php if ($encrypted_serpstack_api_key !== false && !empty(trim($encrypted_serpstack_api_key))) : ?>
                    <input type="password" value="Encrypted serpstack API Key" class="regular-text" disabled />
                <?php else : ?>
                    <input type="password" name="quickpress_ai_serpstack_api_key" class="regular-text" />
                <?php
                  submit_button('Save');
                  endif;
                ?>
            </td>
            <td>
              <?php if ($encrypted_serpstack_api_key !== false && !empty(trim($encrypted_serpstack_api_key))) : ?>
                  <p>Your serpstack API key is encrypted and securely saved.<br />
                  <a href="#" onclick="event.preventDefault(); resetserpstackKey();">Click here</a> to reset it.</p>

                  <script>
                      function resetserpstackKey() {
                          if (confirm('Are you sure you want to reset the serpstack API key? This action cannot be undone.')) {
                              document.getElementById('serpstack-api-key-reset-form').submit();

                              setTimeout(function() {
                                  document.querySelector("input[name='quickpress_ai_serpstack_api_key']").value = '';
                              }, 500);
                          }
                      }
                  </script>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($encrypted_serpstack_api_key !== false && !empty(trim($encrypted_serpstack_api_key))) : ?>
          <tr valign="middle">
              <th style="vertical-align: middle;" scope="row">Generate Keyword Ideas</th>
              <td>
                  <input type="text" id="keyword-input" class="regular-text" placeholder="Enter a topic and click Generate"><br />
                  <button style="margin-top: 10px;" id="keyword-search-btn" class="button button-primary">Generate</button>
              </td>
              <td style="vertical-align: top;">
                  <div id="api-usage-info"><p>Loading API usage...</p></div>
              </td>
          </tr>
          <?php endif; ?>
      </table>
      </form>
      <?php if ($encrypted_serpstack_api_key !== false && !empty(trim($encrypted_serpstack_api_key))) : ?>
          <table class="form-table">
              <tr valign="top">
                  <td style="vertical-align: top;padding-left:0;">
                    <div style="margin:0 10px 0 0;min-height:250px;border-right: 1px solid #eeeeee;">
                        <div id="loading" style="display: none;">
                            <h1>Keyword Ideas for...</h1>
                            <p>Generating...</p>
                        </div>
                        <div id="cache-notification" style="display: none; margin-bottom: 10px;"></div>
                        <div id="results">
                            <h1>Keyword Ideas</h1>
                            <p>Enter a topic and click the "Generate" button to see keyword ideas here.</p>
                        </div>
                    </div>
                  </td>
                  <td style="width:415px;vertical-align: top;">
                      <h1>Saved Keyword Ideas</h1>
                      <div id="saved-ideas"></div>
                  </td>
              </tr>
          </table>
      <?php endif; ?>
  </div>
<?php endif; ?>

  <?php if (!empty(trim($api_key))) : ?>
      <form method="post" id="api-key-reset-form" style="display: none;">
          <?php wp_nonce_field('quickpress_ai_reset_settings', 'quickpress_ai_nonce'); ?>
          <input type="hidden" name="reset_settings" value="1">
      </form>
  <?php endif; ?>
  <?php if ($encrypted_serpstack_api_key !== false && !empty(trim($encrypted_serpstack_api_key))) : ?>
      <form method="post" id="serpstack-api-key-reset-form" style="display: none;">
          <?php wp_nonce_field('quickpress_ai_reset_serpstack_key', 'quickpress_ai_nonce'); ?>
          <input type="hidden" name="reset_serpstack_api_key" value="1">
      </form>
  <?php endif; ?>
</div>
<style>
#results ul {
    list-style: disc; /* Ensure bullets appear */
    margin-left: 20px; /* Add indentation */
}
#results li {
    margin-bottom: 5px; /* Improve spacing */
}
.error-message {
    color: #D9534F; /* Bootstrap 'danger' red */
    font-weight: bold;
    margin-top: 10px;
}
</style>
<script>
jQuery(document).ready(function($) {
  function fetchKeywordData(force_refresh = false) {
      var keyword = $('#keyword-input').val().trim();
      if (!keyword) {
          $('#results').html('<h1>Keyword Ideas</h1><p>Please enter a topic.</p>');
          $('#cache-notification').hide();
          return;
      }

      $('#loading').show();
      $('#results').html('');
      $('#cache-notification').hide();

      $.ajax({
          type: 'POST',
          url: ajaxurl,
          data: {
              action: 'fetch_serpstack_data',
              keyword: keyword,
              refresh: force_refresh ? 1 : 0
          },
          success: function(response) {
              $('#loading').hide();

              if (!response.success) {
                  let errorMessage = response.data && response.data.message ? response.data.message : "An unknown error occurred.";
                  $('#results').html(`<p class="error-message">${errorMessage}</p>`);
                  $('#cache-notification').hide();
                  return;
              }

              if (!response.data) {
                  $('#results').html('<p>No data returned by Serpstack.</p>');
                  $('#cache-notification').hide();
                  return;
              }

              var data = response.data;
              var html = '';

              if ($('#cache-notification').length > 0) {
                  if (!force_refresh && data.saved_date) {
                      let savedDate = new Date(data.saved_date + ' UTC');
                      if (!isNaN(savedDate.getTime())) {
                          let formattedDate = savedDate.toLocaleString(undefined, {
                              year: 'numeric',
                              month: 'long',
                              day: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit',
                              timeZoneName: 'short'
                          });

                          $('#cache-notification').html(`
                              <h1>Keyword Ideas for "${keyword}"</h1>
                              <p>Saved on ${formattedDate}
                                  <button title="Refresh keyword ideas for '${keyword}'" id="refresh-keyword-search-btn" style="margin-left: 0px; background: none; border: none; cursor: pointer;">
                                      <span class="dashicons dashicons-update" style="color: #777777;font-size: 18px; vertical-align: middle;"></span>
                                  </button>
                              </p>
                          `).show();
                      } else {
                          $('#cache-notification').hide();
                      }
                  } else {
                      $('#cache-notification').hide();
                  }
              }

              if (Array.isArray(data.keywords) && data.keywords.length > 0) {
                  html += "<h3>Top Ranking Search Results</h3><ol>";
                  data.keywords.forEach(item => {
                      html += `<li><a href="${item.url}" target="_blank">${item.title}</a></li>`;
                  });
                  html += "</ol>";
              } else {
                  html += "<h3>Top Ranking Search Results</h3><p class='empty-state'>No search results provided by Serpstack.</p>";
              }

              if (Array.isArray(data.related_searches) && data.related_searches.length > 0) {
                  html += "<h3>Related Searches</h3><ul>";
                  data.related_searches.forEach(search => {
                      html += `<li>${search}</li>`;
                  });
                  html += "</ul>";
              } else {
                  html += "<h3>Related Searches</h3><p class='empty-state'>No related searches available.</p>";
              }

              if (Array.isArray(data.paa) && data.paa.length > 0) {
                  html += "<h3>Related Search Questions</h3><ul>";
                  data.paa.forEach(question => {
                      html += `<li>${question}</li>`;
                  });
                  html += "</ul>";
              } else {
                  html += "<h3>Related Search Questions</h3><p class='empty-state'>No related questions provided by Serpstack.</p>";
              }

              if (Array.isArray(data.synonyms) && data.synonyms.length > 0) {
                  html += "<h3>Similar Keywords & Phrases</h3><ul>";
                  data.synonyms.forEach(synonym => {
                      html += `<li>${synonym}</li>`;
                  });
                  html += "</ul>";
              } else {
                  html += "<h3>Similar Keywords & Phrases</h3><p class='empty-state'>No similar keywords or phrases were generated.</p>";
              }

              $('#results').html(html);
              loadSavedIdeas();
              fetchAPIUsage();
          },
          error: function(jqXHR, textStatus, errorThrown) {
              $('#loading').hide();
              let errorDetails = jqXHR.responseText ? jqXHR.responseText : `${textStatus} - ${errorThrown}`;
              $('#results').html(`<p class="error-message">Error fetching data: ${errorDetails}</p>`);
              $('#cache-notification').hide();
          }
      });
    }

    $(document).on('click', '.previous-keyword', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        let keyword = $(this).data('keyword');

        $('#keyword-input').val(keyword);
        fetchKeywordData(false);
    });

    function fetchAPIUsage() {
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: { action: 'quickpress_ai_fetch_api_usage' },
            success: function(response) {

                if (response.success) {
                    let lastUpdated = response.data.last_updated;
                    let formattedDate = 'Unknown Date';

                    if (lastUpdated && lastUpdated !== 'TBD') {

                        let savedDate = new Date(lastUpdated);

                        if (!isNaN(savedDate.getTime())) {
                            formattedDate = savedDate.toLocaleString(undefined, {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true,
                                timeZoneName: 'short'
                            });
                        }
                    }

                    let apiUsageHtml = `
                        <p><strong>serpstack API Requests Remaining:</strong> ${response.data.remaining} / ${response.data.limit}</p>
                        <p><strong>Last Updated:</strong> ${formattedDate}</p>
                    `;
                    jQuery('#api-usage-info').html(apiUsageHtml);
                } else {
                    jQuery('#api-usage-info').html('<p>Failed to retrieve API usage.</p>');
                }
            },
            error: function() {
                jQuery('#api-usage-info').html('<p>Error fetching API usage.</p>');
            }
        });
    }

    jQuery(document).ready(function() {
        fetchAPIUsage();
    });

    function loadSavedIdeas() {
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: { action: 'quickpress_ai_fetch_saved_ideas' },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<ul>';
                    response.data.forEach(item => {
                        let formattedDate = 'Unknown Date';

                        if (item.date && item.date !== 'Unknown Date') {
                            let savedDate = new Date(item.date + ' UTC');
                            if (!isNaN(savedDate)) {
                                formattedDate = savedDate.toLocaleString(undefined, {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hour12: true,
                                    timeZoneName: 'short'
                                });
                            }
                        }

                        html += `<li style="display: flex; flex-direction: column; gap: 2px; padding: 5px 0;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button title="Delete '${item.keyword}'" class="delete-saved-idea" data-hash="${item.hash}" style="background: none; border: none; cursor: pointer; padding: 0;">
                                    <span class="dashicons dashicons-trash" style="color: #777777; font-size: 16px; position: relative; top: 2px;"></span>
                                </button>
                                <a href="#" class="previous-keyword" data-keyword="${item.keyword}" style="flex-grow: 1; font-weight: bold;">${item.keyword}</a>
                            </div>
                            <span class="saved-date" style="font-size: 12px; color: gray; padding-left: 26px;">${formattedDate}</span>
                        </li>`;
                    });
                    html += '</ul>';
                    jQuery('#saved-ideas').html(html);
                } else {
                    jQuery('#saved-ideas').html('<p>No saved ideas, yet!</p>');
                }
            }
        });
    }

    $('#keyword-search-btn').click(function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        fetchKeywordData(false);
    });

    $(document).on('click', '#refresh-keyword-search-btn', function() {
        fetchKeywordData(true);
    });

    loadSavedIdeas();
});
jQuery(document).ready(function($) {
    $(document).on('click', '.delete-saved-idea', function() {
        let hash = $(this).data('hash');
        let listItem = $(this).closest('li');
        let ideaText = listItem.find('.previous-keyword').text().trim();

        if (!confirm(`Are you sure you want to delete the saved idea: "${ideaText}"?`)) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: { action: 'quickpress_ai_delete_saved_idea', hash: hash },
            success: function(response) {
                if (response.success) {
                    listItem.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Failed to delete saved idea.');
                }
            },
            error: function() {
                alert('Error deleting saved idea.');
            }
        });
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const tabs = document.querySelectorAll(".nav-tab");
    const contents = document.querySelectorAll(".tab-content");

    tabs.forEach(tab => {
        tab.addEventListener("click", function (event) {
            event.preventDefault();
            const target = this.getAttribute("data-tab");

            tabs.forEach(t => t.classList.remove("nav-tab-active"));
            contents.forEach(c => c.classList.remove("active"));

            this.classList.add("nav-tab-active");
            document.getElementById(target).classList.add("active");
        });
    });
});
</script>

<!-- Styling -->
<style>
.tab-content { display: none; padding: 20px; background: #fff; border: 1px solid #ccc; margin-top: 10px; }
.tab-content.active { display: block; }
.nav-tab-active { background: #fff; border-bottom: 1px solid transparent; }
</style>
