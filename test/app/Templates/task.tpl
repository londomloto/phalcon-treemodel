<div class="bt-node bt-hbox {{if _last}}bt-last{{/if}}" 
    data-id="{{:wtt_id}}" 
    data-level="{{:wtt_depth}}" 
    data-leaf="{{:wtt_is_leaf}}">
    {{for _elbows}}
        <div class="bt-node-elbow {{:type}}">{{:icon}}</div>
    {{/for}}
    <div class="bt-node-body bt-flex bt-hbox" style="background-color: {{:wtt_bgcolor}}">
        <div class="bt-drag" style="background-color: {{:wtt_bgcolor}}"></div>
        <div class="bt-plugin head bt-hbox"></div>
        <div class="bt-text bt-flex bt-hbox" style="color: {{:wtt_fontcolor}}">{{:wtt_title}}</div>
        <div class="bt-plugin tail bt-hbox"></div>
    </div>
</div>