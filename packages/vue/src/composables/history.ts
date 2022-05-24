import { router } from '../router'
import { ref, toRaw, watch } from 'vue'

/**
 * Returns a ref with a value saved in the history state through Sleightful.
 * The state is linked to a specific browser history entry.
 */
export function useHistoryState<T = any>(key: string, initial: T) {
	const value = ref<T>(router.history.get(key) as T ?? initial)

	watch(value, (value) => {
		router.history.remember(key, toRaw(value))
	}, { immediate: true, deep: true })

	return value
}