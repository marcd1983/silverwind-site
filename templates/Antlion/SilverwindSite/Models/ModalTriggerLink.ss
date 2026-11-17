<%-- Rendered when a ModalTriggerLink is output directly ($MyLink / $Me). A button, not a link: it opens
     <dialog id="$ModalTarget"> on this page (silverwind-blocks' modals script, via data-modal-target) --%>
<% if $exists %>
    <button type="button" class="btn btn-primary" data-modal-target="$ModalTarget" aria-haspopup="dialog">$Title.XML</button>
<% end_if %>
