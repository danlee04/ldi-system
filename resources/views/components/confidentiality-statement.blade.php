{{-- The Statement of Confidentiality itself. The sign-in page shows it in a
     modal and the agreement page in full, so the wording lives only here. --}}
<div {{ $attributes->class('space-y-4 text-sm text-zinc-700 dark:text-zinc-300') }}>
    <p>{{ __('This system holds the personal information of the employees of the Drug Treatment and Rehabilitation Center Caraga: their Personal Data Sheets, family background, education, eligibilities, work experience, and training records. This information is protected by Republic Act No. 10173, the Data Privacy Act of 2012.') }}</p>

    <p>{{ __('By using this system, you agree to:') }}</p>

    <ol class="list-decimal space-y-2 ps-5">
        <li>{{ __('Use employee information only for the official work of your role, and only as much of it as that work needs.') }}</li>
        <li>{{ __('Never disclose, copy, print, download, or share it with anyone who is not authorized to see it.') }}</li>
        <li>{{ __('Keep your account to yourself: never share your password, and log out before you leave a shared computer.') }}</li>
        <li>{{ __('Keep any file you export, such as a Personal Data Sheet, roster, or report, secure, and delete it once it is no longer needed.') }}</li>
        <li>{{ __('Report to HR at once any loss, leak, or misuse of information, or any use of your account that was not yours.') }}</li>
    </ol>

    <p>{{ __('This duty continues after your access ends or you leave the Center. Unauthorized access, disclosure, or misuse of personal information may lead to administrative, civil, and criminal liability under the Data Privacy Act of 2012 and Republic Act No. 6713, the Code of Conduct and Ethical Standards for Public Officials and Employees.') }}</p>
</div>
