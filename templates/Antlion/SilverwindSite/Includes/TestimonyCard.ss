<%-- A Testimony as a quote. Scope: Testimony. Large=1 for the one-at-a-time carousel --%>
<figure class="flex h-full flex-col rounded-box border border-base-300 bg-base-100 p-6 shadow-sm<% if $Large %> items-center p-8 text-center sm:p-12<% end_if %>">
    <svg class="mb-4 size-8 shrink-0 text-primary/40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.6 5C6 6.6 4 9.5 4 13.4V19h6v-6H7c0-2.6 1.3-4.4 3.7-5.4L9.6 5Zm10 0C16 6.6 14 9.5 14 13.4V19h6v-6h-3c0-2.6 1.3-4.4 3.7-5.4L19.6 5Z"/></svg>
    <blockquote class="flex-1 <% if $Large %>text-xl leading-relaxed text-balance sm:text-2xl<% else %>leading-relaxed<% end_if %>">
        <p>$Testimony.XML</p>
    </blockquote>
    <figcaption class="mt-6 font-semibold<% if not $Large %> text-sm<% end_if %>">$Title.XML</figcaption>
</figure>
