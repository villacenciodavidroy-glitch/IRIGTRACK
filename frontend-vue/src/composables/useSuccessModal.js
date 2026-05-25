import { ref } from 'vue'

const defaultTitles = {
  success: 'Success',
  error: 'Error',
  warning: 'Warning',
  info: 'Information'
}

/**
 * Reusable alert/success modal state (pairs with SuccessModal.vue).
 * @example
 * const { show, close, isOpen, message, title, type, buttonText } = useSuccessModal()
 * show({ message: 'Item reissued successfully!', type: 'success' })
 */
export default function useSuccessModal() {
  const isOpen = ref(false)
  const message = ref('')
  const type = ref('success')
  const title = ref('Success')
  const buttonText = ref('OK')

  const show = ({
    message: msg,
    type: modalType = 'success',
    title: modalTitle,
    buttonText: btnText = 'OK'
  } = {}) => {
    type.value = modalType
    message.value = msg ?? ''
    title.value = modalTitle ?? defaultTitles[modalType] ?? 'Notice'
    buttonText.value = btnText
    isOpen.value = true
  }

  const close = () => {
    isOpen.value = false
    message.value = ''
    type.value = 'success'
    title.value = 'Success'
    buttonText.value = 'OK'
  }

  return {
    isOpen,
    message,
    type,
    title,
    buttonText,
    show,
    close
  }
}
