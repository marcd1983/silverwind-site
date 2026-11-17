<%-- A ribbon from the library; nothing outside its dates. Full width spans the screen, otherwise a rounded box in its column --%>
<% if $ActiveRibbon %>$ActiveRibbon.RenderedFor($IsFullBleed)<% end_if %>
